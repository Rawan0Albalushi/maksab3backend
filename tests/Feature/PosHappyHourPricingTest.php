<?php

namespace Tests\Feature;

use App\Http\Controllers\Vendor\POSController;
use App\Models\HappyHour;
use App\Models\HappyHourStore;
use App\Models\Store;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * TC_147 — POS's store-wide discount block called StoreDiscountResolver::vendorDiscount($store)
 * directly, which skips resolve()'s happy_hour > store_discount precedence entirely
 * (vendorDiscount() only ever returns the store's own standing discount). makeOrderDetails()
 * (customer) and makeEditOrderDetails() both already call the happy-hour-aware
 * Helpers::get_store_discount() — makePosOrderDetails() now does too.
 *
 * Note on what this test can and can't catch: the per-line discount_percentage/discount_on_item
 * were already correct even with the bug, because productPayload() -> ProductResource already
 * resolves the item's effective discount through Helpers::product_discount_calculate(...,
 * check_store_discount: true), which is happy-hour-aware independent of this trait. The bug's
 * observable effect is narrower than "no discount applied": the store-wide block's own
 * discount_type/discount_on_product_by relabeling (used for discount-bearer accounting) never
 * fired for a happy-hour-only store, because vendorDiscount() returned null and the block's
 * `isset($storeDiscount)` guard skipped it — leaving the line mislabeled 'product_discount'
 * instead of 'precentage'/'store_discount'. That mislabeling is what this test pins.
 */
class PosHappyHourPricingTest extends TestCase
{
    use DatabaseTransactions;

    private function invokeMakePosOrderDetails(array $carts, Store $store): array
    {
        $controller = app(POSController::class);
        $method = new ReflectionMethod($controller, 'makePosOrderDetails');
        $method->setAccessible(true);

        return $method->invoke($controller, $carts, null, $store);
    }

    private function cartLine(int $itemId): array
    {
        return [
            'item_id' => $itemId, 'id' => $itemId, 'item_type' => 'App\Models\Item',
            'quantity' => 1, 'variant' => null, 'variations' => [], 'variation' => [],
            'add_ons' => [], 'add_on_ids' => [], 'add_on_qtys' => [],
            'bogo_group_id' => null, 'bundle_group_id' => null,
        ];
    }

    public function test_a_running_happy_hour_prices_a_pos_line(): void
    {
        $store = Store::withoutGlobalScopes()->where('status', 1)->whereNotNull('module_id')->first();
        $item = \App\Models\Item::where('store_id', $store?->id)->where('status', 1)->first();

        if (! $store || ! $item) {
            $this->markTestSkipped('need an active store with at least one sellable item');
        }

        $happyHour = new HappyHour();
        $happyHour->module_id = $store->module_id;
        $happyHour->title = 'Test Happy Hour — POS pricing';
        $happyHour->short_description = 'test';
        $happyHour->discount = 25;
        $happyHour->min_order_amount = 0;
        $happyHour->is_permanent = 0;
        $happyHour->duration_type = 'daily';
        $happyHour->start_time = '00:00:00';
        $happyHour->end_time = '23:59:59';
        $happyHour->status = 1;
        $happyHour->admin_id = 1;
        $happyHour->save();

        \DB::table('happy_hour_dates')->insert([
            'happy_hour_id' => $happyHour->id, 'module_id' => $store->module_id,
            'applicable_date' => now()->toDateString(),
            'start_time' => '00:00:00', 'end_time' => '23:59:59', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $enrollment = new HappyHourStore();
        $enrollment->happy_hour_id = $happyHour->id;
        $enrollment->store_id = $store->id;
        $enrollment->status = HappyHourStore::STATUS_APPROVED;
        $enrollment->save();

        $this->assertTrue($happyHour->fresh()->isRunningNow(), 'fixture setup must produce a live happy hour');

        $result = $this->invokeMakePosOrderDetails([$this->cartLine($item->id)], $store);

        $this->assertArrayNotHasKey('status_code', $result, 'must not error: '.json_encode($result));
        $this->assertNotEmpty($result['order_details']);

        $line = $result['order_details'][0];
        $this->assertSame(25.0, (float) $line['discount_percentage'], 'the happy hour discount must be applied to the POS line');
        $this->assertEqualsWithDelta(0.25 * $item->price, (float) $line['discount_on_item'], 0.01);

        // The discriminating assertion: with vendorDiscount() (the bug), the store-wide block's
        // isset($storeDiscount) guard never opens for a happy-hour-only store, so the line keeps
        // its per-item 'product_discount' label instead of being recognized as store-wide. Both
        // the amount above AND this label are correct only with get_store_discount() wired in.
        $this->assertSame('precentage', $line['discount_type'], 'the store-wide happy hour discount must be recognized, not just coincidentally priced right');
        $this->assertSame('store_discount', $line['discount_on_product_by']);
    }
}
