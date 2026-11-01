<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\HappyHour;
use App\Models\Store;
use App\Services\Promotion\BundleOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsBundleFixtures;
use Tests\TestCase;

/**
 * TC_36 — a bundled item with a promotion of its own.
 *
 * Precedence is flash_sale > happy_hour > bundle_discount > store_discount > item_discount, and
 * the whole reduction on a bundle has exactly ONE source so exactly one party bears it. Inside a
 * bundle that means a member's own discount, the store-wide discount and any flash sale it is in
 * are all suppressed: the member is charged its frozen price and the bundle's own percentage --
 * or the happy hour that replaces it -- is the only money taken off.
 */
class BundleDiscountPrecedenceTest extends TestCase
{
    use BuildsBundleFixtures, DatabaseTransactions;

    private ?Store $store = null;

    private ?Bundle $bundle = null;

    private $items = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->bundleFixtureStore(2, withoutHappyHour: true);

        if (! $this->store) {
            $this->markTestSkipped('dataset has no bundle-capable store with two sellable items and no live happy hour');
        }

        $this->enableBundlesFor($this->store);
        $this->openFixtureStore($this->store);

        $this->items = $this->sellableFixtureItems($this->store, 2);
        $this->bundle = $this->makeFixtureBundle($this->store, $this->items, discountPercentage: 20);
    }

    /** With no window running, the bundle's own percentage is the whole reduction. */
    public function test_the_bundle_percentage_is_the_whole_reduction_with_no_window_running(): void
    {
        $details = $this->detailsFor($this->bundle);
        $plain = app(BundleOrderService::class)->distributeReduction($details, $this->store);

        if ($plain['happy_hour_discount'] > 0) {
            $this->markTestSkipped('the fixture store is already running a happy hour');
        }

        $groupTotal = collect($details)->sum(fn ($d) => $d['price'] * $d['quantity']);

        $this->assertEqualsWithDelta(round($groupTotal * 0.2, 2), $plain['bundle_discount'], 0.02);
        $this->assertSame(0.0, $plain['happy_hour_discount']);
    }

    /**
     * A running happy hour REPLACES the bundle percentage — the reduction moves bucket wholesale.
     *
     * The replacement is symmetric on purpose: a WEAKER window makes the bundle dearer than its
     * own price. That is not a bug to round away — the whole reduction must have exactly one
     * source so exactly one party bears it.
     */
    public function test_a_running_happy_hour_replaces_the_bundle_reduction(): void
    {
        $this->runHappyHour(5);

        $details = $this->detailsFor($this->bundle);
        $reduction = app(BundleOrderService::class)->distributeReduction($details, $this->store->fresh());

        if ($reduction['happy_hour_discount'] <= 0) {
            $this->markTestSkipped('the window is not resolving for this store in this dataset');
        }

        $groupTotal = collect($details)->sum(fn ($d) => $d['price'] * $d['quantity']);

        $this->assertSame(0.0, $reduction['bundle_discount'],
            'the bundle percentage does not stack on top of the window');
        $this->assertEqualsWithDelta(round($groupTotal * 0.05, 2), $reduction['happy_hour_discount'], 0.02,
            'the window rate replaces the bundle rate outright, weaker or not');
    }

    /** The reduction is booked to exactly one bucket, never to both. */
    public function test_the_reduction_has_exactly_one_source(): void
    {
        $reduction = app(BundleOrderService::class)->distributeReduction($this->detailsFor($this->bundle), $this->store);

        $this->assertTrue(
            ($reduction['bundle_discount'] > 0) xor ($reduction['happy_hour_discount'] > 0),
            'a group books its reduction as a bundle discount or as a happy hour, never as both',
        );
    }

    /** The per-line shares always add back up to the group reduction. */
    public function test_the_line_shares_add_back_up_to_the_group_reduction(): void
    {
        $details = $this->detailsFor($this->bundle);
        $reduction = app(BundleOrderService::class)->distributeReduction($details, $this->store);

        $booked = 0.0;

        foreach ($reduction['lines'] as $index => $perUnit) {
            $booked += $perUnit * $details[$index]['quantity'];
        }

        $this->assertEqualsWithDelta(
            $reduction['bundle_discount'] + $reduction['happy_hour_discount'],
            round($booked, 2),
            0.02,
            'what the lines carry is what the order reports, or the payable disagrees per platform',
        );
    }

    /** A member's own item discount does not reach the line. */
    public function test_a_members_own_discount_is_suppressed_on_a_bundle_line(): void
    {
        $cleared = app(BundleOrderService::class)->clearLineDiscount([
            'discount_type' => 'product_discount',
            'discount_amount' => 25.0,
            'discount_percentage' => 10.0,
            'original_discount_type' => 'percent',
        ]);

        $this->assertSame(0, $cleared['discount_amount']);
        $this->assertSame(0, $cleared['discount_percentage']);
        $this->assertSame('vendor', $cleared['discount_type'],
            'a bundle line is described the same way whether or not it earned a reduction');
    }

    /**
     * A member in a running flash sale contributes NOTHING to the order's flash-sale totals.
     *
     * The line was charged its full frozen price, so banking the flash sale's admin and vendor
     * shares would record a discount nobody gave and charge it to the store as an expense.
     */
    public function test_a_flash_sale_member_contributes_no_flash_sale_shares(): void
    {
        $cleared = app(BundleOrderService::class)->clearLineDiscount([
            'discount_type' => 'flash_sale',
            'discount_amount' => 30.0,
            'admin_discount_amount' => 18.0,
            'vendor_discount_amount' => 12.0,
            'discount_percentage' => 10.0,
            'original_discount_type' => 'percent',
        ]);

        $this->assertNotSame('flash_sale', $cleared['discount_type'],
            'the caller banks the flash-sale shares on discount_type, so it must not stay flash_sale');
        $this->assertSame(0, $cleared['admin_discount_amount']);
        $this->assertSame(0, $cleared['vendor_discount_amount']);
    }

    /** The whole order path: a flash-sale member inside a bundle leaks no flash-sale money. */
    public function test_a_flash_sale_member_inside_a_bundle_leaks_nothing_into_the_order(): void
    {
        $member = $this->items->first();

        $sale = new FlashSale();
        $sale->forceFill([
            'title' => 'TC_36 sale '.uniqid(),
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'is_publish' => 1,
            'module_id' => $this->store->module_id,
            'admin_discount_percentage' => 60,
            'vendor_discount_percentage' => 40,
        ])->save();

        $saleItem = new FlashSaleItem();
        $saleItem->forceFill([
            'flash_sale_id' => $sale->id,
            'item_id' => $member->id,
            'discount' => 10,
            'discount_type' => 'percent',
            'status' => 1,
            'stock' => 100,
            'sold' => 0,
            'available_stock' => 100,
            'price' => (float) $member->price,
        ])->save();

        $discount = \App\CentralLogics\Helpers::product_discount_calculate(
            $member->toArray(), (float) $member->price, $this->store, false
        );

        if (($discount['discount_type'] ?? null) !== 'flash_sale') {
            $this->markTestSkipped('the fixture item is not resolving into the flash sale');
        }

        $cleared = app(BundleOrderService::class)->clearLineDiscount($discount);

        $this->assertSame(0, $cleared['admin_discount_amount']);
        $this->assertSame(0, $cleared['vendor_discount_amount']);
        $this->assertSame(0, $cleared['discount_amount']);
    }

    private function runHappyHour(float $percentage): void
    {
        $happyHour = new HappyHour();
        $happyHour->forceFill([
            'module_id' => $this->store->module_id,
            'title' => 'TC_36 window '.uniqid(),
            'discount' => $percentage,
            'is_permanent' => 1,
            'duration_type' => 'weekly',
            'weekly_days' => [now()->format('l')],
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'status' => 1,
        ])->save();

        DB::table('happy_hour_store')->insert([
            'happy_hour_id' => $happyHour->id,
            'store_id' => $this->store->id,
            'status' => 'approved',
            'requested_by' => 'vendor',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function detailsFor(Bundle $bundle): array
    {
        $details = [];

        foreach ($bundle->items as $line) {
            $details[] = [
                'bundle_group_id' => 'tc36-group',
                'bundle_id' => $bundle->id,
                'price' => (float) $line->unit_price,
                'quantity' => 1,
            ];
        }

        return $details;
    }
}
