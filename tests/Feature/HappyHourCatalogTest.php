<?php

namespace Tests\Feature;

use App\Models\HappyHour;
use App\Models\HappyHourDate;
use App\Models\HappyHourStore;
use App\Models\Store;
use App\Services\Promotion\HappyHourCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The read surface for happy hours.
 *
 * The cases that matter are the ones where a happy hour must NOT reach someone: the wrong
 * module, an unapproved enrolment, a window that is not open. Each is a way a customer could be
 * shown a discount no store will honour.
 */
class HappyHourCatalogTest extends TestCase
{
    use DatabaseTransactions;

    private HappyHourCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = app(HappyHourCatalog::class);
    }

    /**
     * D2's whole reason for existing: a happy hour is module-scoped, so a grocery window must not
     * reach a food store even where both trade.
     */
    public function test_a_happy_hour_does_not_reach_another_module(): void
    {
        [$store, $happyHour] = $this->makeLiveHappyHour();

        $otherModuleId = DB::table('modules')->where('id', '!=', $happyHour->module_id)->value('id');

        if (! $otherModuleId) {
            $this->markTestSkipped('needs two modules');
        }

        $this->assertNotNull(
            $this->catalog->runningIn([$store->zone_id], $happyHour->module_id),
            'the happy hour should be found in its own module'
        );

        $this->assertNull(
            $this->catalog->runningIn([$store->zone_id], $otherModuleId),
            'the happy hour must not be found in a different module'
        );
    }

    /** A window reaches a customer only through an approved enrolment. */
    public function test_a_pending_enrolment_does_not_make_a_happy_hour_live(): void
    {
        [$store, $happyHour, $enrolment] = $this->makeLiveHappyHour();

        $enrolment->update(['status' => HappyHourStore::STATUS_PENDING]);

        $this->assertNull(
            $this->catalog->runningIn([$store->zone_id], $happyHour->module_id),
            'a pending enrolment must not advertise a discount the store has not agreed to'
        );
    }

    public function test_a_closed_window_is_not_running(): void
    {
        [$store, $happyHour] = $this->makeLiveHappyHour();

        // Move today's window into the past.
        HappyHourDate::where('happy_hour_id', $happyHour->id)->update([
            'start_time' => '00:00:01',
            'end_time' => '00:00:02',
        ]);

        $this->assertNull($this->catalog->runningIn([$store->zone_id], $happyHour->module_id));
    }

    /**
     * The banner counts its stores through the same rule that decides it is running, so a live
     * banner can never say zero stores.
     */
    public function test_window_payload_counts_only_servable_stores(): void
    {
        [$store, $happyHour, $enrolment] = $this->makeLiveHappyHour();

        $payload = $this->catalog->windowPayload($happyHour, [$store->zone_id], $happyHour->module_id);

        $this->assertSame(1, $payload['store_count']);
        $this->assertGreaterThan(0, $payload['remaining_seconds'], 'a live window has time left on it');
        $this->assertIsInt($payload['remaining_seconds'], 'a countdown of 1199.05 seconds helps nobody');

        $enrolment->update(['status' => HappyHourStore::STATUS_REJECTED]);

        $this->assertSame(
            0,
            $this->catalog->windowPayload($happyHour->fresh(), [$store->zone_id], $happyHour->module_id)['store_count']
        );
    }

    /** Never negative, however long ago the window shut. */
    public function test_remaining_seconds_never_goes_negative(): void
    {
        [$store, $happyHour] = $this->makeLiveHappyHour();

        HappyHourDate::where('happy_hour_id', $happyHour->id)->update([
            'start_time' => '00:00:01',
            'end_time' => '00:00:02',
        ]);

        $payload = $this->catalog->windowPayload($happyHour->fresh(), [$store->zone_id], $happyHour->module_id);

        $this->assertSame(0, $payload['remaining_seconds']);
    }

    public function test_stores_query_lists_only_enrolled_stores_in_the_module(): void
    {
        [$store, $happyHour] = $this->makeLiveHappyHour();

        $ids = $this->catalog
            ->storesQuery([$store->zone_id], $happyHour->module_id)
            ->pluck('stores.id')
            ->all();

        $this->assertContains($store->id, $ids);
    }

    /**
     * @return array{0:Store,1:HappyHour,2:HappyHourStore}
     */
    private function makeLiveHappyHour(): array
    {
        $store = Store::where('status', 1)->first();

        if (! $store || ! $store->zone_id || ! $store->module_id) {
            $this->markTestSkipped('dataset has no active store with a zone and module');
        }

        $happyHour = HappyHour::create([
            'module_id' => $store->module_id,
            'title' => 'catalog probe',
            'discount' => 15,
            'duration_type' => HappyHour::DURATION_DAILY,
            'is_permanent' => 0,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'status' => 1,
        ]);

        // Daily and weekly schedules are expanded into dated rows; this is today's.
        HappyHourDate::create([
            'happy_hour_id' => $happyHour->id,
            'module_id' => $happyHour->module_id,
            'applicable_date' => now()->toDateString(),
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'status' => 1,
        ]);

        $enrolment = HappyHourStore::create([
            'happy_hour_id' => $happyHour->id,
            'store_id' => $store->id,
            'status' => HappyHourStore::STATUS_APPROVED,
            'joined_at' => now(),
        ]);

        return [$store, $happyHour, $enrolment];
    }
}
