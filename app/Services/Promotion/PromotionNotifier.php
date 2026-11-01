<?php

namespace App\Services\Promotion;

use App\Mail\PromotionEnrollmentMail;
use App\Models\Admin;
use App\Models\Store;
use App\Support\Notification\NotificationMessages;
use App\Support\Notification\SendNotification;
use Illuminate\Support\Facades\Cache;

/**
 * Every Happy Hour and BOGO enrolment transition, in one place.
 *
 * The enrolment flow is written twice -- the vendor panel has its own copy of the write logic and
 * the vendor API goes through {@see BogoOfferVendorService} / {@see HappyHourVendorService} -- so a
 * notification wired at the call sites would have to be wired twice and would drift. Both sides
 * call this instead.
 *
 * Three audiences, because that is what exists:
 *   store    -- push (copy from `notification_messages`) and mail (templates from `email_templates`)
 *   admin    -- mail only; 6amMart has no admin push channel, exactly as CampaignController found
 *   customer -- push only, and only when an enrolment goes LIVE. A customer has no stake in a
 *               request being raised or an invitation being declined; they have one in a bundle
 *               they can now buy. Sent to the zone's customer topic rather than per device,
 *               because a zone holds thousands of customers and a broadcast must not become one
 *               HTTP call each inside the admin's save request.
 *
 * Every method swallows its own failures. A mail server that is down must never roll back an
 * enrolment that was already saved, and every one of these is called after the write.
 */
class PromotionNotifier
{
    public const BOGO = 'bogo';

    public const HAPPY_HOUR = 'happy_hour';

    /**
     * A store asked to join. The admin is told someone is waiting; the store gets its own copy for
     * the record, which is what makes "did that submit?" answerable without opening the panel.
     */
    public function storeRequestedJoin(string $promotion, $offer, $store): void
    {
        $this->guard(function () use ($promotion, $offer, $store) {
            $this->pushToStore($promotion, $store, $this->messageKey($promotion, '{p}_join_request'), $offer, translate('messages.Request submitted'));

            $this->mailStore($promotion, $store, $offer, $this->mailType($promotion, 'request'));

            $this->mailAdmin($promotion, $store, $offer, $this->messageKey($promotion, 'admin_{p}_join_request'));
        });
    }

    /** The admin assigned this store an offer; it is waiting on the store to answer. */
    public function adminInvitedStore(string $promotion, $offer, $store): void
    {
        $this->guard(function () use ($promotion, $offer, $store) {
            $this->pushToStore($promotion, $store, $this->messageKey($promotion, 'store_{p}_invitation'), $offer, translate('messages.New invitation'));
        });
    }

    /** The admin approved a request the store raised. The bundle is buyable from this moment. */
    public function adminApproved(string $promotion, $offer, $store): void
    {
        $this->guard(function () use ($promotion, $offer, $store) {
            $this->pushToStore($promotion, $store, $this->messageKey($promotion, '{p}_join_accepted'), $offer, translate('messages.Request approved'));

            $this->mailStore($promotion, $store, $offer, $this->mailType($promotion, 'approve'));

            $this->broadcastToCustomers($promotion, $offer, $store);
        });
    }

    /** The admin rejected a request the store raised, with a reason the store can act on. */
    public function adminRejected(string $promotion, $offer, $store): void
    {
        $this->guard(function () use ($promotion, $offer, $store) {
            $this->pushToStore($promotion, $store, $this->messageKey($promotion, '{p}_join_denied'), $offer, translate('messages.Request declined'));

            $this->mailStore($promotion, $store, $offer, $this->mailType($promotion, 'deny'));
        });
    }

    /** A store accepted an admin invitation, so the enrolment is live from now on. */
    public function enrollmentWentLive(string $promotion, $offer, $store): void
    {
        $this->guard(function () use ($promotion, $offer, $store) {
            $this->pushToStore($promotion, $store, $this->messageKey($promotion, 'store_{p}_join_approval'), $offer, translate('messages.Offer is live'));

            $this->mailAdmin($promotion, $store, $offer, $this->messageKey($promotion, 'admin_{p}_invitation_accepted'));

            $this->broadcastToCustomers($promotion, $offer, $store);
        });
    }

    /**
     * A store declined an admin invitation.
     *
     * The admin is told for the same reason it is told about a withdrawal: the enrolment simply
     * sits rejected otherwise, and silence would mean either "not answered yet" or "said no on
     * Tuesday".
     */
    public function storeDeclinedInvitation(string $promotion, $offer, $store): void
    {
        $this->guard(function () use ($promotion, $offer, $store) {
            $this->mailAdmin($promotion, $store, $offer, $this->messageKey($promotion, 'admin_{p}_invitation_declined'));
        });
    }

    /** The store left an offer it was enrolled in. Every removal is total, so this is not a pause. */
    public function storeWithdrew(string $promotion, $offer, $store): void
    {
        $this->guard(function () use ($promotion, $offer, $store) {
            $this->pushToStore($promotion, $store, $this->messageKey($promotion, 'store_{p}_offer_withdrawn'), $offer, translate('messages.Offer left'));

            $this->mailAdmin($promotion, $store, $offer, $this->messageKey($promotion, 'admin_withdrawn'));
        });
    }

    /** The admin removed the store from an offer it had been running. */
    public function adminRemovedStore(string $promotion, $offer, $store): void
    {
        $this->guard(function () use ($promotion, $offer, $store) {
            $this->pushToStore($promotion, $store, $this->messageKey($promotion, 'store_{p}_join_rejection'), $offer, translate('messages.Removed from offer'));
        });
    }

    /**
     * Push, if this store has the channel on and a device to send to.
     *
     * The copy comes from the seeded `notification_messages` row for the store's own module type,
     * so an operator editing it in the panel changes what is actually sent.
     */
    private function pushToStore(string $promotion, $store, string $messageKey, $offer, string $title): void
    {
        $store = $this->hydrate($store);

        if (! $store?->vendor?->firebase_token) {
            return;
        }

        if (! SendNotification::channelEnabled('store', $this->settingKey($promotion, 'store'), 'push_notification_status', $store->id)) {
            return;
        }

        $data = NotificationMessages::promotionEnrollment(
            key: $messageKey,
            moduleType: $store->module?->module_type,
            replace: [
                'offerTitle' => $offer?->title ?? '',
                'storeName' => $store->name ?? '',
            ],
            title: $title,
            // The offer this is about. Without it the payload names a transition and nothing to
            // apply it to, so tapping the notification could only ever open a list and let the
            // vendor find the offer again themselves.
            extra: ['data_id' => (string) ($offer?->id ?? '')],
            // The promotion, not the transition -- the same two strings StackFood sends for every
            // vendor push of these features. The transition survives as `notification_key`.
            payloadType: $this->payloadType($promotion),
        );

        SendNotification::pushToVendor($store->vendor_id, $store->vendor->firebase_token, $data);
    }

    /**
     * Tell the customers of this zone that an offer they can buy has gone live.
     *
     * Sent to a topic rather than per device: a zone holds thousands of customers, and one HTTP
     * call each inside the admin's approve request is not a thing that finishes.
     *
     * The topic is `zone_{zone}_module_{module}_customer`, NOT the zone's stored
     * `customer_wise_topic`. Two reasons, and both are bugs the stored one caused:
     *
     *   1. `zone_{id}_customer` is not unique to this platform. StackFood builds the identical
     *      string, and both installs point at the same Firebase project (`stackmart-500c7`), so a
     *      BOGO going live here raised a notification on StackFood phones. FCM topics are global
     *      to a project -- there is no namespacing by app or by backend -- and the message carries
     *      an `alert` block, so the OS shows it before either app can filter it.
     *   2. It was zone-wide but not module-aware: a grocery offer reached a customer browsing
     *      food. `module_id` was in the payload for the app to filter on, which meant every
     *      install paid to receive news it would then discard.
     *
     * Adding the module fixes both at once, and is the convention documented in
     * `documentation/bogo-happy-hour-notification.md`. **The customer app must subscribe to this
     * name**; the guide is updated with it.
     *
     * **Once per (offer, zone).** Approving ten stores in one zone is one piece of news to a
     * customer, not ten; without this a bulk approval is ten pushes to every phone in the zone.
     * The claim is held for a day, which is longer than any run of approvals and shorter than any
     * offer worth announcing twice. `Cache::add()` is atomic, so two simultaneous approvals cannot
     * both win it.
     *
     * Failure to claim is not an error -- it means someone else already said it.
     */
    private function broadcastToCustomers(string $promotion, $offer, $store): void
    {
        $store = $this->hydrate($store);
        $topic = $this->customerTopic($store);

        if (! $topic || ! $offer?->id) {
            return;
        }

        if (! SendNotification::channelEnabled('customer', $this->customerSettingKey($promotion), 'push_notification_status')) {
            return;
        }

        $claimed = Cache::add(
            "promotion_customer_broadcast:{$promotion}:{$offer->id}:{$store->zone_id}",
            true,
            now()->addDay(),
        );

        if (! $claimed) {
            return;
        }

        $data = NotificationMessages::promotionEnrollment(
            key: $this->customerMessageKey($promotion),
            moduleType: $store->module?->module_type,
            replace: [
                'offerTitle' => $offer->title ?? '',
                'storeName' => $store->name ?? '',
            ],
            title: $promotion === self::BOGO
                ? translate('messages.New BOGO offer')
                : translate('New happy hour offer'),
            // `data_id` is the offer; `store_id` is where it went live. A zone topic reaches every
            // customer in the zone, so the app needs both to open the right screen.
            extra: [
                'data_id' => (string) $offer->id,
                'store_id' => (string) ($store->id ?? ''),
                'module_id' => (string) ($store->module_id ?? ''),
                'zone_id' => (string) ($store->zone_id ?? ''),
            ],
        );

        SendNotification::pushToTopic($data, $topic, $this->payloadType($promotion));
    }

    /**
     * The topic a customer of this zone AND module listens on.
     *
     * Both parts are required. Without the zone the news travels further than the store serves;
     * without the module it reaches customers of a vertical the offer does not exist in -- and,
     * because the zone-only name is the same string StackFood builds, it left this platform's
     * Firebase project unable to tell the two products apart.
     *
     * Null when either is missing, which stops the broadcast rather than sending it somewhere
     * broader than intended. A promotion nobody hears about is recoverable; one that lands on
     * another product's customers is not.
     */
    private function customerTopic($store): ?string
    {
        $zoneId = $store?->zone_id;
        $moduleId = $store?->module_id;

        if (! $zoneId || ! $moduleId) {
            return null;
        }

        return 'zone_'.$zoneId.'_module_'.$moduleId.'_customer';
    }

    private function mailStore(string $promotion, $store, $offer, string $emailType): void
    {
        $store = $this->hydrate($store);

        if (! config('mail.status') || ! $store) {
            return;
        }

        if (! SendNotification::canSendMail($emailType.'_mail_status_store', 'store', $this->settingKey($promotion, 'store'), $store->id)) {
            return;
        }

        SendNotification::mail(
            $store->getRawOriginal('email'),
            new PromotionEnrollmentMail($store->name, $emailType, $offer?->title),
        );
    }

    /**
     * Mail the admin, with the seeded admin copy as the subject.
     *
     * Using that row rather than a literal keeps the `admin_*` messages an operator can edit from
     * being decorative -- there is no admin push channel for them to drive instead.
     */
    private function mailAdmin(string $promotion, $store, $offer, string $messageKey): void
    {
        $store = $this->hydrate($store);
        $admin = Admin::where('role_id', 1)->first();

        if (! config('mail.status') || ! $admin) {
            return;
        }

        if (! SendNotification::channelEnabled('admin', $this->settingKey($promotion, 'admin'), 'mail_status')) {
            return;
        }

        $subject = NotificationMessages::promotionEnrollment(
            key: $messageKey,
            moduleType: $store?->module?->module_type,
            replace: [
                'offerTitle' => $offer?->title ?? '',
                'storeName' => $store?->name ?? '',
            ],
        )['description'] ?? '';

        SendNotification::mail(
            $admin->getRawOriginal('email'),
            new PromotionEnrollmentMail($store?->name, $this->mailType($promotion, 'request'), $offer?->title, $subject),
        );
    }

    /**
     * The notification-settings row these transitions are gated by.
     *
     * P1 collapsed StackFood's four keys per promotion into one per audience, so a single toggle
     * governs invite, approve, reject and withdraw rather than four that could disagree.
     */
    private function settingKey(string $promotion, string $audience): string
    {
        return $audience === 'admin'
            ? ($promotion === self::BOGO ? 'bogo_offer_request' : 'happy_hour_request')
            : ($promotion === self::BOGO ? 'bogo_offer_enrollment' : 'happy_hour_enrollment');
    }

    /** The customer-audience `notification_settings` row this broadcast is gated by. */
    private function customerSettingKey(string $promotion): string
    {
        return $promotion === self::BOGO ? 'customer_bogo_offer' : 'customer_happy_hour_offer';
    }

    /** The seeded `notification_messages` key carrying the customer-facing copy. */
    private function customerMessageKey(string $promotion): string
    {
        return $promotion === self::BOGO ? 'customer_bogo_offer_live' : 'customer_happy_hour_offer_live';
    }

    /**
     * The `type` every push of these features carries -- vendor and customer alike.
     *
     * Exactly the two strings StackFood sends: `bogo_offer` and `happy_hour`. One vocabulary across
     * both products, so an app serving both branches once.
     *
     * It names the PROMOTION, never the transition. The vendor sees seven events and the customer
     * one, and putting the transition here made the vendor payload announce itself as
     * `store_bogo_invitation` -- a name StackFood's app has never heard of. The transition moved to
     * `notification_key`, which is additive and which StackFood has no equivalent of.
     *
     * Not to be confused with the `customer_happy_hour_offer` settings row or the
     * `customer_happy_hour_offer_live` message key: different namespaces, and they keep their names.
     */
    private function payloadType(string $promotion): string
    {
        return $promotion === self::BOGO ? 'bogo_offer' : 'happy_hour';
    }

    /**
     * The seeded `notification_messages` key for a transition.
     *
     * A map rather than string concatenation, because the 2026_09_02 seeders are not symmetric:
     * BOGO's admin withdrawal is `admin_bogo_offer_withdrawn` while happy hour's is
     * `admin_happy_hour_withdrawn`. Concatenating a rule would have silently missed the second and
     * fallen back to a humanised key -- the exact failure this whole class exists to end.
     */
    private function messageKey(string $promotion, string $event): string
    {
        $isBogo = $promotion === self::BOGO;

        return match ($event) {
            'admin_withdrawn' => $isBogo ? 'admin_bogo_offer_withdrawn' : 'admin_happy_hour_withdrawn',
            default => str_replace('{p}', $promotion, $event),
        };
    }

    /** The seeded email_templates.email_type for this promotion and outcome. */
    private function mailType(string $promotion, string $outcome): string
    {
        return $promotion.'_'.$outcome;
    }

    /**
     * Relations the pushes and the mail need, loaded once and only when missing.
     *
     * `zone` joined the list with the customer broadcast: the topic is a zone column, and reading
     * it off an unloaded relation would be one query per notification rather than one per store.
     */
    private function hydrate($store): ?Store
    {
        if (! $store instanceof Store) {
            return $store ? Store::with(['vendor', 'module', 'zone'])->find($store) : null;
        }

        return $store->loadMissing(['vendor', 'module', 'zone']);
    }

    private function guard(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            // Never let a notification failure undo an enrolment that is already written.
            info(['promotion_notify' => $e->getMessage()]);
        }
    }
}
