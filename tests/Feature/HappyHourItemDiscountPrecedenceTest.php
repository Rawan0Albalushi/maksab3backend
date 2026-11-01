<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\HappyHour;
use App\Models\HappyHourStore;
use App\Models\Item;
use App\Models\Store;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * TC_141 sub-points 1-2 (Happy Hour business logic edge cases) — a running happy hour must
 * REPLACE an item's own discount, never stack on top of it, and the item must revert to its own
 * discount once the happy hour's schedule ends.
 */
class HappyHourItemDiscountPrecedenceTest extends TestCase
{
    use DatabaseTransactions;

    private function makeHappyHour(Store $store, string $startTime, string $endTime): HappyHour
    {
        $happyHour = new HappyHour();
        $happyHour->module_id = $store->module_id;
        $happyHour->title = 'TC_141 Test';
        $happyHour->short_description = 'test';
        $happyHour->discount = 25;
        $happyHour->min_order_amount = 0;
        $happyHour->is_permanent = 0;
        $happyHour->duration_type = 'daily';
        $happyHour->start_time = $startTime;
        $happyHour->end_time = $endTime;
        $happyHour->status = 1;
        $happyHour->admin_id = 1;
        $happyHour->save();

        \DB::table('happy_hour_dates')->insert([
            'happy_hour_id' => $happyHour->id, 'module_id' => $store->module_id,
            'applicable_date' => now()->toDateString(),
            'start_time' => $startTime, 'end_time' => $endTime, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $enrollment = new HappyHourStore();
        $enrollment->happy_hour_id = $happyHour->id;
        $enrollment->store_id = $store->id;
        $enrollment->status = HappyHourStore::STATUS_APPROVED;
        $enrollment->save();

        return $happyHour;
    }

    public function test_the_happy_hour_discount_replaces_the_items_own_discount_and_reverts_after_it_ends(): void
    {
        $store = Store::where('status', 1)->whereNotNull('module_id')->first();
        $item = Item::where('store_id', $store?->id)->where('status', 1)->first();

        if (! $store || ! $item) {
            $this->markTestSkipped('need an active store with at least one sellable item');
        }

        $item->discount = 10;
        $item->discount_type = 'percent';
        $item->save();

        $happyHour = $this->makeHappyHour($store, '00:00:00', '23:59:59');

        $duringDiscount = Helpers::product_discount_calculate($item, $item->price, $store, true);

        $this->assertSame(25.0, (float) $duringDiscount['discount_percentage'], 'the happy hour discount must replace the item discount, not stack with it (25, not 35)');

        $happyHour->start_time = '00:00:00';
        $happyHour->end_time = '00:00:01';
        $happyHour->save();
        \DB::table('happy_hour_dates')->where('happy_hour_id', $happyHour->id)->update(['end_time' => '00:00:01']);

        $afterDiscount = Helpers::product_discount_calculate($item->fresh(), $item->price, $store, true);

        $this->assertSame(10.0, (float) $afterDiscount['discount_percentage'], 'the item must revert to its own discount once the happy hour schedule ends');
    }
}
