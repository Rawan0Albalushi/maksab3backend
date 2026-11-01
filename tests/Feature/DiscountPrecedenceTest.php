<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Discount;
use App\Models\HappyHour;
use App\Models\HappyHourDate;
use App\Models\HappyHourStore;
use App\Models\Item;
use App\Models\Store;
use App\Scopes\ZoneScope;
use App\Services\Promotion\StoreDiscountResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The precedence table, asserted once:
 *
 *     flash_sale  >  happy_hour  >  store_discount  >  item_discount
 *
 * Four discount systems now share one price, and the failure mode is silent: a happy hour that
 * reported itself as a store discount would be billed to the wrong side and vanish from the
 * earning breakdown, while still showing the customer the right number.
 */
class DiscountPrecedenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_happy_hour_outranks_the_vendors_own_discount(): void
    {
        [$store] = $this->makeStoreWithBoth(vendorRate: 10, happyHourRate: 25);

        $resolved = Helpers::get_store_discount($store);

        $this->assertSame('happy_hour', $resolved['source']);
        $this->assertSame(25.0, $resolved['discount'], 'the happy hour rate wins outright');
    }

    /**
     * Outranks, not adds to. The two are separate systems: `discounts` is the vendor's tool and
     * a happy hour is an admin campaign the vendor opted into. Stacking would charge the store
     * twice for one window.
     */
    public function test_a_happy_hour_replaces_rather_than_stacks(): void
    {
        [$store] = $this->makeStoreWithBoth(vendorRate: 10, happyHourRate: 25);

        $this->assertSame(25.0, Helpers::get_store_discount($store)['discount']);
        $this->assertNotSame(35.0, Helpers::get_store_discount($store)['discount']);
    }

    public function test_the_vendor_rate_applies_when_no_happy_hour_runs(): void
    {
        [$store, $happyHour] = $this->makeStoreWithBoth(vendorRate: 10, happyHourRate: 25);

        // Close the window without touching the enrolment.
        HappyHourDate::where('happy_hour_id', $happyHour->id)->update([
            'start_time' => '00:00:01',
            'end_time' => '00:00:02',
        ]);

        $resolved = Helpers::get_store_discount($store->fresh(['discount', 'happyHourEnrollments.happyHour']));

        $this->assertSame('store_discount', $resolved['source']);
        $this->assertSame(10.0, $resolved['discount']);
    }

    /** A pending enrolment is not agreement, so it must not discount anything. */
    public function test_an_unapproved_enrolment_does_not_apply_a_happy_hour(): void
    {
        [$store, $happyHour] = $this->makeStoreWithBoth(vendorRate: 10, happyHourRate: 25);

        HappyHourStore::where('happy_hour_id', $happyHour->id)
            ->update(['status' => HappyHourStore::STATUS_PENDING]);

        $resolved = Helpers::get_store_discount($store->fresh(['discount', 'happyHourEnrollments.happyHour']));

        $this->assertSame('store_discount', $resolved['source']);
    }

    /**
     * The label is what every downstream consumer names the promotion from, so it decides which
     * side is billed. Getting the number right and the label wrong is still wrong.
     */
    public function test_the_source_label_reaches_the_item_price_calculation(): void
    {
        [$store, , $item] = $this->makeStoreWithBoth(vendorRate: 10, happyHourRate: 25);

        $result = Helpers::product_discount_calculate($item, 200.0, $store);

        $this->assertSame(50.0, $result['discount_amount'], '25% of 200');
        $this->assertSame('store_discount', $result['discount_type'],
            'the existing fold still labels a store-wide win this way; §11.1 reads source separately');
    }

    /**
     * Both discount functions must consult the same resolver. They differ legitimately --
     * services have no flash sale branch and label their own rate 'service_discount' -- so this
     * asserts the store-wide fold agrees, not that the whole return matches.
     */
    public function test_product_and_service_pricing_agree_on_the_store_wide_rate(): void
    {
        [$store, , $item] = $this->makeStoreWithBoth(vendorRate: 10, happyHourRate: 25);

        $service = (object) ['discount' => 0, 'discount_type' => 'percent'];

        $product = Helpers::product_discount_calculate($item, 200.0, $store);
        $serviceResult = Helpers::service_discount_calculate((array) $service, 200.0, $store);

        $this->assertSame(
            $product['discount_amount'],
            $serviceResult['discount_amount'],
            'the same store-wide rate must produce the same amount on both paths'
        );
    }

    public function test_no_discount_at_all_resolves_to_null(): void
    {
        $store = Store::with('discount')->first();

        if (! $store) {
            $this->markTestSkipped('dataset has no store');
        }

        Discount::where('store_id', $store->id)->delete();

        $this->assertNull(app(StoreDiscountResolver::class)->resolve($store->fresh('discount')));
    }

    /**
     * @return array{0:Store,1:HappyHour,2:Item}
     */
    private function makeStoreWithBoth(float $vendorRate, float $happyHourRate): array
    {
        $item = Item::withoutGlobalScope(ZoneScope::class)->with(['module', 'store'])->first();
        $store = $item?->store;

        if (! $store || ! $store->zone_id) {
            $this->markTestSkipped('dataset has no item with a store in a zone');
        }

        Store::whereKey($store->id)->update(['status' => 1]);

        Discount::where('store_id', $store->id)->delete();
        Discount::create([
            'store_id' => $store->id,
            'discount' => $vendorRate,
            'discount_type' => 'percent',
            'min_purchase' => 0,
            'max_discount' => 0,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
        ]);

        $happyHour = HappyHour::create([
            'module_id' => $store->module_id,
            'zone_id' => $store->zone_id,
            'title' => 'precedence probe',
            'discount' => $happyHourRate,
            'duration_type' => HappyHour::DURATION_DAILY,
            'is_permanent' => 0,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'status' => 1,
        ]);

        HappyHourDate::create([
            'happy_hour_id' => $happyHour->id,
            'zone_id' => $happyHour->zone_id,
            'module_id' => $happyHour->module_id,
            'applicable_date' => now()->toDateString(),
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'status' => 1,
        ]);

        HappyHourStore::create([
            'happy_hour_id' => $happyHour->id,
            'store_id' => $store->id,
            'status' => HappyHourStore::STATUS_APPROVED,
            'joined_at' => now(),
        ]);

        return [$store->fresh(['discount', 'happyHourEnrollments.happyHour']), $happyHour, $item];
    }
}
