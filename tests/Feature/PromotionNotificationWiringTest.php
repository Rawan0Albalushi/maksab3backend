<?php

namespace Tests\Feature;

use App\Models\NotificationMessage;
use App\Services\Promotion\PromotionNotifier;
use App\Support\Notification\NotificationMessages;
use App\Support\Notification\StoreNotificationSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * That the copy and the code still refer to each other.
 *
 * This feature shipped with 17 seeded `notification_messages` rows and six `email_templates` that
 * nothing referenced: the copy existed, the settings screen listed it, and not one transition sent
 * anything. The inverse fails just as quietly -- a key the notifier asks for but nobody seeded
 * resolves to a humanised fallback and pushes "Store happy hour offer withdrawn" at a vendor.
 *
 * Both directions are asserted here, because both were real. The seeders are not symmetric between
 * the two promotions (BOGO's admin withdrawal is `admin_bogo_offer_withdrawn`, happy hour's is
 * `admin_happy_hour_withdrawn`), which is exactly the kind of thing string concatenation gets wrong
 * without ever raising anything.
 */
class PromotionNotificationWiringTest extends TestCase
{
    use DatabaseTransactions;

    /** Every transition, and the message key it is supposed to reach for. */
    private const TRANSITIONS = [
        'storeRequestedJoin' => ['{p}_join_request', 'admin_{p}_join_request'],
        'adminInvitedStore' => ['store_{p}_invitation'],
        'adminApproved' => ['{p}_join_accepted'],
        'adminRejected' => ['{p}_join_denied'],
        // enrollmentWentLive is the one transition with a THIRD audience: besides telling the
        // store and the admin, it broadcasts to the zone's customers. That key comes from
        // customerMessageKey() rather than messageKey(), but both promotions spell it the same
        // way, so the {p} template resolves it correctly and the copy is covered here like any
        // other. Leaving it out was why the seeded customer rows read as orphaned.
        'enrollmentWentLive' => ['store_{p}_join_approval', 'admin_{p}_invitation_accepted', 'customer_{p}_offer_live'],
        'storeDeclinedInvitation' => ['admin_{p}_invitation_declined'],
        'storeWithdrew' => ['store_{p}_offer_withdrawn', 'admin_withdrawn'],
        'adminRemovedStore' => ['store_{p}_join_rejection'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        NotificationMessages::forgetPromotionCopy();
    }

    /**
     * Every key the notifier can ask for has copy seeded for every module type that runs
     * promotions. A missing row does not throw -- it falls back -- so nothing but this notices.
     */
    public function test_every_key_the_notifier_uses_is_seeded(): void
    {
        // Read from the flag rather than re-listing the types, which is the rule CLAUDE.md sets:
        // config('module.module_type') is the list of names, and each name keys its own config.
        $moduleTypes = collect(config('module.module_type'))
            ->filter(fn ($type) => config('module.'.$type.'.promotions'))
            ->values();

        $this->assertNotEmpty($moduleTypes, 'no module type declares the promotions flag');

        $missing = [];

        foreach ([PromotionNotifier::BOGO, PromotionNotifier::HAPPY_HOUR] as $promotion) {
            foreach ($this->keysFor($promotion) as $key) {
                foreach ($moduleTypes as $moduleType) {
                    $exists = NotificationMessage::where('key', $key)
                        ->whereIn('module_type', [$moduleType, 'all'])
                        ->exists();

                    if (! $exists) {
                        $missing[] = $key.' ('.$moduleType.')';
                    }
                }
            }
        }

        $this->assertSame([], $missing, 'notifier keys with no seeded copy: '.implode(', ', $missing));
    }

    /**
     * The other direction: every seeded promotion row is reachable from some transition.
     *
     * Copy nobody sends is the bug this feature shipped with, and it is invisible from the panel --
     * the settings screen renders an editable row either way.
     */
    public function test_every_seeded_promotion_message_is_reachable(): void
    {
        $reachable = collect([PromotionNotifier::BOGO, PromotionNotifier::HAPPY_HOUR])
            ->flatMap(fn ($promotion) => $this->keysFor($promotion))
            ->unique();

        $seeded = NotificationMessage::query()
            ->where(fn ($q) => $q->where('key', 'like', '%bogo%')->orWhere('key', 'like', '%happy_hour%'))
            ->distinct()
            ->pluck('key');

        $this->assertNotEmpty($seeded, 'no promotion notification copy is seeded at all');

        $orphaned = $seeded->diff($reachable)->values()->all();

        $this->assertSame([], $orphaned, 'seeded copy no transition sends: '.implode(', ', $orphaned));
    }

    /** The seeded copy is what gets sent, with its placeholders filled in. */
    public function test_the_seeded_copy_is_used_and_interpolated(): void
    {
        $data = NotificationMessages::promotionEnrollment(
            key: 'store_bogo_invitation',
            moduleType: 'grocery',
            replace: ['offerTitle' => 'Buy 2 Get 1', 'storeName' => 'Acme Mart'],
            title: 'New invitation',
        );

        $this->assertSame('New invitation', $data['title']);
        $this->assertStringContainsString('Acme Mart', $data['description']);
        $this->assertStringContainsString('Buy 2 Get 1', $data['description']);
        $this->assertStringNotContainsString('{', $data['description'], 'a placeholder survived');
    }

    /** An unseeded key degrades to something readable rather than an empty push. */
    public function test_an_unknown_key_falls_back_rather_than_sending_nothing(): void
    {
        $data = NotificationMessages::promotionEnrollment('a_key_nobody_seeded', 'grocery');

        $this->assertNotSame('', trim($data['description']));
    }

    /** Editing the row changes what is sent -- the point of reading copy from the database. */
    public function test_edited_copy_is_what_gets_sent(): void
    {
        $row = NotificationMessage::where('key', 'store_bogo_invitation')
            ->where('module_type', 'grocery')
            ->first();

        if (! $row) {
            $this->markTestSkipped('promotion notification copy is not seeded in this dataset');
        }

        $row->update(['message' => 'Rewritten by an operator: {offerTitle}']);
        NotificationMessages::forgetPromotionCopy();

        $data = NotificationMessages::promotionEnrollment(
            'store_bogo_invitation',
            'grocery',
            ['offerTitle' => 'Half Price Tuesday']
        );

        $this->assertSame('Rewritten by an operator: Half Price Tuesday', $data['description']);
    }

    /**
     * The per-store toggle exists for every key the notifier gates on.
     *
     * NotificationGate consults store_notification_settings per store and returns 0 for a key it
     * has no row for, so a key missing from this list silences that transition for every store,
     * however the global settings are configured -- and the toggle never appears on the vendor's
     * notification screen either. The gate installs the whole list when it misses a key, so being
     * in it is also what backfills stores onboarded before the promotion existed.
     */
    public function test_every_gated_key_is_in_the_canonical_store_list(): void
    {
        $method = new ReflectionMethod(PromotionNotifier::class, 'settingKey');
        $method->setAccessible(true);
        $notifier = app(PromotionNotifier::class);

        $storeKeys = collect(StoreNotificationSettings::getStoreNotificationSetupData(1))
            ->pluck('key')
            ->all();

        foreach ([PromotionNotifier::BOGO, PromotionNotifier::HAPPY_HOUR] as $promotion) {
            $this->assertContains(
                $method->invoke($notifier, $promotion, 'store'),
                $storeKeys,
                $promotion.' has no per-store toggle, so no store can ever be notified about it'
            );
        }
    }

    /** The admin half is gated on the global table, which the P1 migration seeds. */
    public function test_every_gated_admin_key_is_seeded(): void
    {
        $method = new ReflectionMethod(PromotionNotifier::class, 'settingKey');
        $method->setAccessible(true);
        $notifier = app(PromotionNotifier::class);

        foreach ([PromotionNotifier::BOGO, PromotionNotifier::HAPPY_HOUR] as $promotion) {
            $key = $method->invoke($notifier, $promotion, 'admin');

            $this->assertTrue(
                DB::table('notification_settings')->where('key', $key)->where('type', 'admin')->exists(),
                $key.' is gated on but never seeded, so the admin is told nothing'
            );
        }
    }

    /** Resolve a transition's keys through the notifier's own map, asymmetries included. */
    private function keysFor(string $promotion): array
    {
        $method = new ReflectionMethod(PromotionNotifier::class, 'messageKey');
        $method->setAccessible(true);
        $notifier = app(PromotionNotifier::class);

        return collect(self::TRANSITIONS)
            ->flatten()
            ->map(fn ($event) => $method->invoke($notifier, $promotion, $event))
            ->unique()
            ->values()
            ->all();
    }
}
