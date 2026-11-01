<?php

namespace App\Traits\Promotion;

use App\Models\BogoOffer;
use App\Models\BogoOfferStore;
use App\Models\Cart;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resolving a bundle, judging whether it can be ordered, and clearing the ones that cannot.
 *
 * The composition root of the rulebook: the availability, pricing and cart-inspection traits
 * answer individual questions, and bogoUnavailableReason() asks them in the order that produces
 * a sentence a customer can act on.
 */
trait HandlesBogoBundles
{
    use HandlesFrozenLines;
    use HandlesBogoCartInspection;

    /** A bundle by offer and store, either of which may arrive as an id or a slug. */
    protected function resolveBogoBundle($offerKey, $storeKey, array $zoneIds = []): ?BogoOfferStore
    {
        $offer = BogoOffer::where(fn ($q) => $q->where('id', $offerKey)->orWhere('slug', $offerKey))->first();

        if (! $offer) {
            return null;
        }

        return BogoOfferStore::where('bogo_offer_id', $offer->id)
            ->approved()
            ->with(['items.item', 'store', 'bogoOffer'])
            ->whereHas('store', function ($q) use ($storeKey, $zoneIds) {
                $q->where(fn ($s) => $s->where('id', $storeKey)->orWhere('slug', $storeKey));

                if ($zoneIds) {
                    $q->whereIn('zone_id', $zoneIds);
                }
            })
            ->first();
    }

    /**
     * A bundle by its own id -- the bogo_offer_store row.
     *
     * This is the identifier every read endpoint hands back as bundle_id, and it names an offer
     * at one store, which an offer id alone cannot do when several stores run the same offer.
     */
    protected function resolveBogoBundleById($bundleId, array $zoneIds = []): ?BogoOfferStore
    {
        return BogoOfferStore::where('id', $bundleId)
            ->approved()
            ->with(['items.item', 'store', 'bogoOffer'])
            ->when($zoneIds, fn ($q) => $q->whereHas('store', fn ($s) => $s->whereIn('zone_id', $zoneIds)))
            ->first();
    }

    /**
     * Is this bundle gone for good, rather than unavailable for the moment?
     *
     * The cart keeps a bundle that is only temporarily out of reach -- the store is closed, an
     * item's serving window has not opened, stock has run down, the customer has one use left --
     * because all of those come back and the customer would rightly expect their cart to still
     * hold what they put in it.
     *
     * These do not come back: the offer was deleted or switched off, its window closed, the
     * store left it or was removed from it, or the store itself was switched off. A bundle in
     * one of those states can never be ordered as it stands, so it is taken out of the cart
     * instead of sitting there priced at zero and failing at checkout.
     *
     * A missing enrolment counts: it is what deleting the offer leaves behind, and what removing
     * a store from an offer leaves behind.
     */
    protected function bogoBundleIsGone(?BogoOfferStore $enrollment): bool
    {
        if (! $enrollment) {
            return true;
        }

        $offer = $enrollment->bogoOffer;

        return ! $offer
            || ! $offer->status
            || ($offer->end_date && $offer->end_date->lessThan(now()))
            || $enrollment->status !== BogoOfferStore::STATUS_APPROVED
            || ! $enrollment->store
            || ! $enrollment->store->status;
    }

    /**
     * Drop every bundle the cart holds that is gone for good, and answer how many went.
     *
     * Run when the cart is read, so a bundle that died on a clock rather than on a click -- an
     * offer whose window closed -- is cleared without waiting for someone to act, and so a cart
     * orphaned before this existed heals itself the next time it is opened.
     */
    protected function purgeDeadBogoBundles($userId, int $isGuest): int
    {
        $dead = [];

        foreach ($this->bogoGroupsInCart($userId, $isGuest) as $group) {
            if ($this->bogoBundleIsGone($group['enrollment'])) {
                $dead = array_merge($dead, $group['rows']->pluck('id')->all());
            }
        }

        if (! $dead) {
            return 0;
        }

        Cart::whereIn('id', $dead)->delete();

        return count($dead);
    }

    /**
     * Why this bundle cannot be ordered, or null when it can.
     *
     * Returned as a message rather than a boolean because the client shows it against the
     * flagged group in the cart, and the same text explains a refused add.
     *
     * $at is the moment the order is actually fulfilled. It defaults to now, which is right for
     * the cart and for an instant order, but a scheduled order has to be judged against its
     * slot: a bundle booked for next Tuesday is worthless if the offer expires on Monday, and
     * the customer has to be told at checkout rather than at delivery. Only the offer's own date
     * window moves with it -- status, enrolment, stock and usage are read as they stand now,
     * because a future value of any of them is unknowable.
     *
     * CarbonInterface, not Illuminate\Support\Carbon: callers hand over whichever Carbon they
     * already have, and Carbon\Carbon is not an instance of the Illuminate subclass.
     */
    protected function bogoUnavailableReason(
        BogoOfferStore $enrollment,
        int $bundleQuantity,
        $userId,
        int $isGuest,
        ?string $phone = null,
        ?CarbonInterface $at = null,
        ?string $orderType = null
    ): ?string {
        $offer = $enrollment->bogoOffer;
        $at = $at ?: now();
        $isScheduled = $at->greaterThan(now());

        if (! $offer || ! $offer->status) {
            return translate('messages.This_offer_is_no_longer_available');
        }

        // An offer runs for the order types it was published for. The read endpoints filter on
        // this when the client passes order_type, but that is a browsing convenience -- nothing
        // stops a take-away-only offer being checked out as a delivery without this.
        if (BogoOffer::orderTypesEnabled() && $orderType && ! $this->bogoServesOrderType($offer, $orderType)) {
            return translate('messages.This_offer_is_not_available_for_this_order_type');
        }

        if ($offer->start_date && $offer->start_date->greaterThan($at)) {
            return $isScheduled
                ? translate('messages.This_offer_does_not_start_until_after_your_scheduled_time')
                : translate('messages.This offer has not started yet');
        }

        if ($offer->end_date && $offer->end_date->lessThan($at)) {
            return $isScheduled
                ? translate('messages.This_offer_will_have_ended_by_your_scheduled_time')
                : translate('This offer has already ended');
        }

        if ($enrollment->status !== BogoOfferStore::STATUS_APPROVED) {
            return translate('messages.This_store_is_no_longer_part_of_the_offer');
        }

        if (! $enrollment->store || ! $enrollment->store->status) {
            return translate('messages.This_store_is_currently_unavailable');
        }

        // The whole-offer cap counts bundles, so N copies need N left.
        if ($offer->usage_limit_total !== null
            && ($offer->total_uses + $bundleQuantity) > $offer->usage_limit_total) {
            return translate('messages.This_offer_has_reached_its_usage_limit');
        }

        $remaining = $this->bogoRemainingUses($offer, $userId, $isGuest, $phone);

        if ($remaining !== null && $bundleQuantity > $remaining) {
            return $remaining > 0
                ? translate('messages.You_can_only_use_this_offer').' '.$remaining.' '.translate('messages.more_times')
                : translate('messages.You_have_already_used_this_offer_the_maximum_number_of_times');
        }

        return $this->bogoItemsUnavailableReason($enrollment, $bundleQuantity);
    }

    /**
     * Does the offer run for this order type?
     *
     * An offer with no order types set is unrestricted. 'delivery' and 'home_delivery' are the
     * same thing written two ways -- the panels store the first, older records and some clients
     * send the second -- so either matches either.
     *
     * Narrower than the source, which also knew dine_in: 6amMart has no dine-in concept at all.
     */
    protected function bogoItemsUnavailableReason($enrollment, int $bundleQuantity): ?string
    {
        return $this->linesUnavailableReason(
            $enrollment->items, $bundleQuantity, 'messages.Is no longer available'
        );
    }

    /**
     * Whether the cart's held rows for one group still match this enrolment's CURRENT combination.
     *
     * Mirrors HandlesBundleCart::bundleMembersChanged() line for line -- same identity-and-copies
     * comparison, same reason for existing: an admin or vendor can rework which items an approved
     * enrolment sells (HandlesBogoEnrollment::syncEnrollmentItems() deletes and recreates the
     * frozen lines), and a cart holding the OLD selection would otherwise be silently priced and
     * served as whatever the offer says today -- worse than Bundle's gap, since nothing here told
     * the customer anything had changed at all.
     *
     * Calls into HandlesBogoPricing::bogoLineIdentity() for the identity key, which already
     * normalises both the frozen-line shape ({variations: [...]}) and the cart-row shape
     * (variation stored singular, possibly as a JSON string) the same way price lookups already
     * rely on -- so a held row and its enrolled line hash identically when nothing changed.
     * Requires the composing class to also `use HandlesBogoPricing` (BogoCartService and
     * BogoOrderService, the only two callers, both already do).
     */
    protected function bogoCombinationChanged(Collection $heldRows, BogoOfferStore $enrollment): bool
    {
        $held = [];

        foreach ($heldRows as $row) {
            $type = ($row->is_free_item ?? false) ? 'get' : 'buy';
            $identity = $this->bogoLineIdentity($type, $row->item_id, $row->variation, $row->add_on_ids);

            $held[$identity] = ($held[$identity] ?? 0) + max(1, (int) $row->quantity);
        }

        // Grouped by identity BEFORE comparison, and summed rather than read line by line: an
        // offer can enrol the same item twice over on one side as two separate rows, and
        // comparing $enrollment->items raw, unset()ing $held after the FIRST matching line, read
        // the second occurrence as a line the held cart no longer had -- reporting "changed" on
        // an enrolment nobody had reworked, permanently, however many times the group was removed
        // and re-added (see HandlesBundleCart::bundleMembersChanged(), which had the identical
        // bug). Summing here first mirrors how $held above already sums duplicate cart rows.
        $required = [];

        foreach ($enrollment->items as $line) {
            $identity = $this->bogoLineIdentity($line->type, $line->item_id, $line->variations, $line->add_on_ids);
            $required[$identity] = ($required[$identity] ?? 0) + max(1, (int) $line->quantity);
        }

        $copies = null;

        foreach ($required as $identity => $perCopy) {
            if (! isset($held[$identity])) {
                return true;
            }

            if ($held[$identity] % $perCopy !== 0) {
                return true;
            }

            $lineCopies = intdiv($held[$identity], $perCopy);

            if ($copies !== null && $copies !== $lineCopies) {
                return true;
            }

            $copies = $lineCopies;
            unset($held[$identity]);
        }

        // Anything left over is a line the cart holds that the enrolment no longer lists.
        return $held !== [];
    }

    /**
     * Clears any of this customer's EXISTING groups of this offer at this store that no longer
     * match the enrolment's current combination, before a fresh copy is added.
     *
     * The BOGO twin of HandlesBundleCart::purgeStaleBundleGroups() -- same reasoning: a stale
     * group can never be ordered as it stands, so leaving it beside a freshly-added, correct one
     * only means checkout keeps refusing the correct one too. See BogoOrderService::blockingReason(),
     * which checks every group in the cart and blocks on the first stale one it finds.
     */
    protected function purgeStaleBogoGroups($userId, int $isGuest, BogoOfferStore $enrollment): void
    {
        $groups = Cart::where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->where('bogo_offer_id', $enrollment->bogo_offer_id)
            ->where('store_id', $enrollment->store_id)
            ->get()
            ->groupBy('bogo_group_id');

        foreach ($groups as $groupId => $groupRows) {
            if ($this->bogoCombinationChanged($groupRows, $enrollment)) {
                Cart::where('user_id', $userId)
                    ->where('is_guest', $isGuest)
                    ->where('bogo_group_id', $groupId)
                    ->delete();
            }
        }
    }

    protected function bogoServesOrderType(BogoOffer $offer, string $orderType): bool
    {
        // Every offer runs for every order type while the feature is switched off.
        if (! BogoOffer::orderTypesEnabled()) {
            return true;
        }

        $types = $offer->order_types ?: [];

        if (! $types) {
            return true;
        }

        $aliases = match ($orderType) {
            'delivery', 'home_delivery' => ['delivery', 'home_delivery'],
            default => [$orderType],
        };

        return (bool) array_intersect($aliases, $types);
    }
}
