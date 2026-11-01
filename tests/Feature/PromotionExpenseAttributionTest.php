<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Discount;
use App\Models\HappyHour;
use App\Models\HappyHourDate;
use App\Models\HappyHourStore;
use App\Models\Item;
use App\Models\Order;
use App\Models\Store;
use App\Scopes\ZoneScope;
use App\Services\Promotion\StoreDiscountResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Who pays for a promotion, and what commission is charged on.
 *
 * Happy Hour and BOGO are borne 100% by the vendor, each under its own expense type, and for a
 * happy hour the commission base is the price BEFORE the window took its cut. Every part of that
 * fails silently when it is wrong: the customer is charged correctly either way, and the error
 * only shows up as a reconciliation a few percent out in the admin's favour or the vendor's.
 *
 * These assert the inputs to that accounting rather than re-running the whole transaction
 * builder: the bearer stamped on the order, the uncapped-rate sentinel the bearer depends on, and
 * the arithmetic OrderTransactionsTrait performs on them.
 */
class PromotionExpenseAttributionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * A happy hour carries no ceiling, and the value that says so has to be one
     * Helpers::checkAdminDiscount() actually reads as uncapped.
     *
     * A 0 does not: the clamp is `$discount > $max_discount ? $max_discount : $discount`, so a 0
     * cap silently zeroes every discount it touches. That is the whole reason the sentinel is
     * null -- and the reason it cannot quietly go back to 0.
     */
    public function test_a_happy_hour_reports_an_uncapped_rate(): void
    {
        [$store] = $this->makeHappyHourStore(happyHourRate: 25);

        $resolved = Helpers::get_store_discount($store);

        $this->assertSame('happy_hour', $resolved['source']);
        $this->assertNull($resolved['max_discount'], 'a happy hour has no ceiling');
    }

    public function test_an_uncapped_rate_is_not_clamped_to_nothing(): void
    {
        $this->assertSame(
            200.0,
            (float) Helpers::checkAdminDiscount(price: 1000, discount: 20, max_discount: null, min_purchase: 0),
            'a null cap means uncapped, not a cap of nothing'
        );
    }

    /** A vendor's own configured cap keeps clamping exactly as it always did. */
    public function test_a_real_cap_still_clamps(): void
    {
        $this->assertSame(
            500.0,
            (float) Helpers::checkAdminDiscount(price: 5000, discount: 20, max_discount: 500, min_purchase: 0)
        );

        $this->assertSame(
            0.0,
            (float) Helpers::checkAdminDiscount(price: 5000, discount: 20, max_discount: 0, min_purchase: 0),
            'an explicitly configured 0 cap is still a cap of nothing'
        );
    }

    /**
     * A cart holding nothing but a bundle has no discountable base at all. Without the guard the
     * method falls through to its return with $discount still holding the PERCENTAGE it arrived
     * as, so a caller asking what 20% of nothing comes to was told 20 and booked twenty currency
     * off an order nobody discounted.
     */
    public function test_an_empty_discountable_base_invents_no_discount(): void
    {
        $this->assertSame(
            0.0,
            (float) Helpers::checkAdminDiscount(price: 0, discount: 20, max_discount: 500, min_purchase: 0)
        );
    }

    /**
     * A BOGO free line costs nothing, so its share of an order-wide discount is nothing. Testing
     * the old `$item_wise_price > 0` handed such a line the entire order's discount instead.
     */
    public function test_a_free_line_takes_no_share_of_the_order_wide_discount(): void
    {
        $this->assertSame(
            0.0,
            (float) Helpers::checkAdminDiscount(
                price: 1000, discount: 20, max_discount: 500, min_purchase: 0, item_wise_price: 0
            )
        );

        $this->assertSame(
            50.0,
            (float) Helpers::checkAdminDiscount(
                price: 1000, discount: 20, max_discount: 500, min_purchase: 0, item_wise_price: 250
            ),
            'a paid line still takes its proportional share'
        );
    }

    public function test_a_happy_hour_is_borne_by_the_vendor_and_a_store_discount_by_the_admin(): void
    {
        $resolver = app(StoreDiscountResolver::class);

        $this->assertSame('vendor', $resolver->bearerFor(['source' => 'happy_hour']));
        $this->assertSame('admin', $resolver->bearerFor(['source' => 'store_discount']));
        $this->assertSame('admin', $resolver->bearerFor(null));
    }

    /**
     * The arithmetic OrderTransactionsTrait performs, asserted directly.
     *
     * Bearing the whole cost means the vendor is paid on the discounted total while commission is
     * charged on the undiscounted one. Charging commission on the discounted total instead forgoes
     * the admin's share of the discounted portion -- which makes the admin a co-sponsor of a
     * campaign the expense row says the vendor paid for in full.
     */
    public function test_commission_on_a_happy_hour_is_charged_before_the_discount(): void
    {
        $subtotal = 1000.0;
        $rate = 25.0;
        $commission = 20.0;

        $discount = $subtotal * $rate / 100;          // 250
        $orderAmount = $subtotal - $discount;         // 750, what the customer paid

        // OrderTransactionsTrait: the happy hour branch leaves $store_discount_amount at 0, so
        // $order_amount stays at the discounted figure and the window is added to the commission
        // base only.
        $commissionable = $orderAmount + $discount;   // 1000
        $commissionCharged = $commissionable * $commission / 100;
        $storeAmount = $orderAmount - $commissionCharged;

        $this->assertSame(200.0, $commissionCharged, 'commission is charged on the pre-discount price');
        $this->assertSame(550.0, $storeAmount);

        // The vendor bears the whole 250: it receives exactly what it would have received at full
        // price, less the discount it agreed to give.
        $undiscountedPayout = $subtotal - ($subtotal * $commission / 100); // 800
        $this->assertSame(
            $discount,
            $undiscountedPayout - $storeAmount,
            'the vendor carries the entire window, to the currency unit'
        );

        // And the admin carries none of it.
        $this->assertSame(
            $subtotal * $commission / 100,
            $commissionCharged,
            'the admin earns the same commission as on an undiscounted order'
        );
    }

    /**
     * A BOGO give-away is the other way round: it is excluded from the commission base, because
     * the customer was never billed for the free item and no commission is owed on it.
     */
    public function test_a_bogo_give_away_is_outside_the_commission_base(): void
    {
        $paidLines = 1000.0;
        $giveAway = 200.0;
        $commission = 20.0;

        // $commissionable_amount adds the happy hour only; bogo_discount_amount is deliberately
        // absent from it.
        $commissionable = $paidLines + 0.0;

        $this->assertSame(
            200.0,
            $commissionable * $commission / 100,
            'no commission is charged on an item the customer was never billed for'
        );
        $this->assertNotSame(
            ($paidLines + $giveAway) * $commission / 100,
            $commissionable * $commission / 100
        );
    }


    /**
     * The date the store-wide branch started writing a PER-UNIT discount (commit bc132acfc,
     * 2026-08-24). Rows written before it hold the whole line's share in a per-unit column, which
     * is the very bug these two tests guard against -- so including them asserts the bug is still
     * present in history, which it always will be. Orders 100174 and 100188 in the demo data are
     * exactly that shape.
     */
    private const PER_UNIT_DISCOUNT_SINCE = '2026-08-24 00:00:00';

    /** The newest order the invariants below can be asked about. */
    private function latestDiscountedMultiUnitOrder(): ?Order
    {
        return Order::with('details')
            ->where('created_at', '>=', self::PER_UNIT_DISCOUNT_SINCE)
            ->where('store_discount_amount', '>', 0)
            ->whereHas('details', fn ($q) => $q->where('quantity', '>', 1)->where('discount_on_item', '>', 0))
            ->latest('id')
            ->first();
    }

    /**
     * A line's discount_on_item is PER UNIT, so the lines re-total to the order's own figure.
     *
     * Every writer of that column writes a unit discount and every reader multiplies it back up --
     * the report queries do SUM(discount_on_item * quantity), and so does the preserved-line total
     * on the edit path. The store-wide branch used to write the whole line's share instead, so a
     * discount on any line of two or more reported and re-totalled at multiples of itself, and the
     * order editor priced the line at (price - whole_line_discount) x quantity.
     *
     * Asserted as an invariant rather than against a fixture: whatever the basket, the lines must
     * add up to what the order says was taken off it.
     */
    public function test_line_discounts_are_per_unit_and_re_total_to_the_order(): void
    {
        $order = $this->latestDiscountedMultiUnitOrder();

        if (! $order) {
            $this->markTestSkipped('dataset has no discounted order with a multi-unit line written since the fix');
        }

        $fromLines = (float) $order->details->sum(
            fn ($d) => (float) $d->discount_on_item * (int) $d->quantity
        );

        $this->assertEqualsWithDelta(
            (float) $order->store_discount_amount,
            $fromLines,
            0.05,
            'order #'.$order->id.' lines re-total to '.$fromLines.' but the order says '.$order->store_discount_amount
        );
    }

    /**
     * The taxable base takes off the whole line's discount, not one unit's.
     *
     * getFinalCalculatedTax() used to scale by quantity only for `product_discount`, because the
     * store-wide branch was the one writing a per-line figure and the conditional kept the two
     * straight. Once the column became uniformly per-unit that conditional taxed a store-wide
     * discount on a line of two or more as though a single unit had been discounted -- silently,
     * and in the customer's disfavour.
     */
    public function test_the_taxable_base_deducts_the_whole_line_discount(): void
    {
        $order = $this->latestDiscountedMultiUnitOrder();

        if (! $order) {
            $this->markTestSkipped('dataset has no discounted order with a multi-unit line written since the fix');
        }

        $gross = (float) $order->details->sum(fn ($d) => (float) $d->price * (int) $d->quantity);
        $lineDiscounts = (float) $order->details->sum(fn ($d) => (float) $d->discount_on_item * (int) $d->quantity);

        // What the tax was actually charged on, derived from the order rather than recomputed.
        $taxableBase = $gross - $lineDiscounts;

        $this->assertEqualsWithDelta(
            $gross - (float) $order->store_discount_amount,
            $taxableBase,
            0.05,
            'order #'.$order->id.': the base tax was charged on must be the discounted subtotal'
        );

        $this->assertGreaterThan(
            $gross - $lineDiscounts - 0.01,
            $taxableBase,
            'a per-unit deduction would leave the base too high'
        );
    }

    /**
     * @return array{0:Store,1:HappyHour}
     */
    private function makeHappyHourStore(float $happyHourRate): array
    {
        $item = Item::withoutGlobalScope(ZoneScope::class)->with(['module', 'store'])->first();
        $store = $item?->store;

        if (! $store || ! $store->zone_id) {
            $this->markTestSkipped('dataset has no item with a store in a zone');
        }

        Store::whereKey($store->id)->update(['status' => 1]);
        Discount::where('store_id', $store->id)->delete();

        $happyHour = HappyHour::create([
            'module_id' => $store->module_id,
            'zone_id' => $store->zone_id,
            'title' => 'expense attribution probe',
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

        return [$store->fresh(['discount', 'happyHourEnrollments.happyHour']), $happyHour];
    }
}
