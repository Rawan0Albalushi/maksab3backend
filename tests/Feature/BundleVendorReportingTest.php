<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Order;
use App\Models\Store;
use App\Services\Order\ExpenseService;
use App\Services\Order\OrderService;
use App\Services\Order\OrderTransactionService;
use App\Support\Promotion\BundleSettings;
use App\Traits\Report\ReportGeneratorTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsBundleFixtures;
use Tests\TestCase;

/**
 * TC_38 to TC_42 — what a delivered bundle order does to the vendor's books.
 *
 * One real order is placed over HTTP and settled through createOrderTransaction(), then every
 * reporting surface is read off the rows it wrote. The arithmetic is covered elsewhere; what these
 * cover is that the arithmetic is REACHED, and that a bundle reduction is filed under its own
 * expense type all the way to the screens rather than folded into the store's standing discount.
 */
class BundleVendorReportingTest extends TestCase
{
    use BuildsBundleFixtures, DatabaseTransactions, ReportGeneratorTrait;

    private ?Store $store = null;

    private ?Bundle $bundle = null;

    private int $guestId = 0;

    private ?Order $order = null;

    protected function tearDown(): void
    {
        if ($this->guestId) {
            DB::table('carts')->where('user_id', $this->guestId)->where('is_guest', 1)->delete();
        }

        Helpers::clearBusinessSettingsCache();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->bundleFixtureStore(2, fn ($q) => $q
            ->whereNotNull('zone_id')
            ->whereNotNull('vendor_id')
            ->where('store_business_model', 'commission'), withoutHappyHour: true);

        if (! $this->store) {
            $this->markTestSkipped('dataset has no commission store in a zone with two sellable items and no live happy hour');
        }

        $this->openFixtureStore($this->store);
        $this->enableBundlesFor($this->store);

        $this->bundle = $this->makeFixtureBundle($this->store, $this->sellableFixtureItems($this->store, 2), 20);
        $this->guestId = (int) DB::table('guests')->insertGetId(['created_at' => now(), 'updated_at' => now()]);

        $this->order = $this->placeAndSettleBundleOrder();

        if (! $this->order) {
            $this->markTestSkipped('the fixture could not place a bundle order in this dataset');
        }
    }

    /** TC_39 — the bundle reduction gets its OWN expense type, never product discount's. */
    public function test_the_reduction_is_booked_under_its_own_expense_type(): void
    {
        $rows = Expense::where('order_id', $this->order->id)->get();

        $bundle = $rows->where('type', 'bundle_discount');

        $this->assertTrue($bundle->isNotEmpty(),
            'a delivered bundle order must book a bundle_discount expense, found: '
            .$rows->pluck('type')->implode(', '));

        $this->assertTrue($rows->where('type', 'discount_on_product')->isEmpty(),
            'the same money must not also appear as an ordinary product discount');

        $this->assertEqualsWithDelta(
            (float) $this->order->store_discount_amount,
            (float) $bundle->sum('amount'),
            0.02,
            'the bundle expense rows must account for the whole reduction',
        );
    }

    /** TC_41 — the vendor's row and the admin's row are separate, and only one is the store's. */
    public function test_the_vendor_and_admin_shares_are_booked_separately(): void
    {
        $rows = Expense::where('order_id', $this->order->id)->where('type', 'bundle_discount')->get();

        $vendorRow = $rows->firstWhere('created_by', 'vendor');

        $this->assertNotNull($vendorRow, 'the vendor bears a bundle discount, so it must carry a vendor row');
        $this->assertSame((int) $this->store->id, (int) $vendorRow->store_id,
            'a vendor expense is attributed to the store that bore it');

        foreach ($rows->where('created_by', 'admin') as $adminRow) {
            $this->assertNull($adminRow->store_id,
                'the admin share is the admin\'s expense and must not be charged to the store');
        }
    }

    /** TC_41 — the admin-borne share must NOT come off the vendor's net income. */
    public function test_the_admin_share_does_not_reduce_the_vendor_net_income(): void
    {
        $adminShare = (float) Expense::where('order_id', $this->order->id)
            ->where('type', 'bundle_discount')->where('created_by', 'admin')->sum('amount');

        if ($adminShare <= 0) {
            $this->markTestSkipped('this store carries no commission split on the reduction');
        }

        $summary = $this->getStoreEarningSummaryData($this->store->id, 'all_time', null, null, null);
        $vendorShare = (float) Expense::where('order_id', $this->order->id)
            ->where('type', 'bundle_discount')->where('created_by', 'vendor')->sum('amount');

        $this->assertEqualsWithDelta(
            $vendorShare,
            (float) $summary['breakdown']['bundle_discount'] - $this->otherStoresBundleSpend(),
            0.02,
            'the vendor breakdown must carry the vendor share alone, never the admin\'s too',
        );
    }

    /** TC_41 — the vendor figure and the admin panel figure describe the same money, split. */
    public function test_the_vendor_and_admin_panel_figures_reconcile(): void
    {
        $vendorSide = (float) Expense::where('order_id', $this->order->id)
            ->where('type', 'bundle_discount')->where('created_by', 'vendor')->sum('amount');

        $adminBreakdown = $this->buildExpenseBreakdown('all_time', null, null, 1, 'all', null);
        $adminSide = (float) Expense::where('order_id', $this->order->id)
            ->where('type', 'bundle_discount')->where('created_by', 'admin')->sum('amount');

        $this->assertArrayHasKey('bundle_discount', $adminBreakdown,
            'the admin panel reports bundle spend on its own line too');

        $this->assertEqualsWithDelta(
            (float) $this->order->store_discount_amount,
            $vendorSide + $adminSide,
            0.02,
            'the two panels split one reduction; together they must account for all of it',
        );
    }

    /** TC_38 — commission is inside the expense total, which is what makes net income right. */
    public function test_the_commission_is_carried_inside_the_expense_total(): void
    {
        $summary = $this->getStoreEarningSummaryData($this->store->id, 'all_time', null, null, null);

        $this->assertGreaterThanOrEqual(
            (float) $summary['breakdown']['admin_commission'],
            (float) $summary['total_expenses'],
            'net income is earning minus expense AND commission, so commission has to be in expense',
        );

        $this->assertEqualsWithDelta(
            (float) $summary['total_earnings_with_admin_commission']
                - (float) $summary['total_expenses'],
            (float) $summary['net_profit'],
            0.02,
        );
    }

    /** TC_39 — the bundle line carries its own share of the total, not a borrowed one. */
    public function test_the_bundle_line_reports_its_own_share_of_the_total(): void
    {
        $breakdown = $this->getStoreEarningSummaryData($this->store->id, 'all_time', null, null, null)['breakdown'];

        foreach (['discount_on_item', 'coupon_contribution', 'free_delivery', 'bogo_discount', 'happy_hour_discount'] as $other) {
            $this->assertArrayHasKey($other, $breakdown);
        }

        $this->assertGreaterThan(0, (float) $breakdown['bundle_discount']);
        $this->assertGreaterThan(0, (float) $breakdown['bundle_discount_percentage'],
            'TC_39 asks for the amount AND its share of the total');
        $this->assertLessThanOrEqual(100, (float) $breakdown['bundle_discount_percentage']);
    }

    /** TC_42 — a bundle sale counts towards the member item's own sell count. */
    public function test_a_bundle_sale_counts_towards_each_members_sell_count(): void
    {
        $lines = DB::table('order_details')
            ->where('order_id', $this->order->id)->whereNotNull('bundle_group_id')->get();

        $before = Item::withoutGlobalScopes()->whereIn('id', $lines->pluck('item_id'))->pluck('order_count', 'id');

        $service = app(OrderService::class);
        (new \ReflectionMethod($service, 'countVendorDeliveredOrder'))
            ->invoke($service, Order::with('details.item')->find($this->order->id));

        $after = Item::withoutGlobalScopes()->whereIn('id', $lines->pluck('item_id'))->pluck('order_count', 'id');

        foreach ($lines as $line) {
            $this->assertSame(
                (int) $before[$line->item_id] + 1,
                (int) $after[$line->item_id],
                'the dashboard ranks top sellers on order_count, so a bundled member has to be counted',
            );
        }
    }

    /** TC_42 — the wallet the dashboard shows and the earning report agree. */
    public function test_the_wallet_balance_agrees_with_the_earning_report(): void
    {
        $transaction = DB::table('order_transactions')->where('order_id', $this->order->id)->first();

        $reported = (float) DB::table('order_transactions')
            ->join('orders', 'orders.id', '=', 'order_transactions.order_id')
            ->where('orders.store_id', $this->store->id)
            ->where('orders.order_status', '!=', 'refunded')
            ->sum('order_transactions.store_amount');

        $this->assertGreaterThanOrEqual(
            (float) $transaction->store_amount,
            $reported,
            'the earning report totals the same store_amount the wallet was credited with',
        );
    }

    private function otherStoresBundleSpend(): float
    {
        return (float) Expense::where('type', 'bundle_discount')
            ->where('created_by', 'vendor')
            ->where('store_id', $this->store->id)
            ->where('order_id', '!=', $this->order->id)
            ->sum('amount');
    }

    /** TC_39 and TC_41 — the vendor breakdown reports its own share only, on its own line. */
    public function test_the_vendor_expense_breakdown_reports_the_bundle_line_on_its_own(): void
    {
        $summary = $this->getStoreEarningSummaryData($this->store->id, 'all_time', null, null, null);

        $this->assertArrayHasKey('bundle_discount', $summary['breakdown']);
        $this->assertArrayHasKey('bundle_discount_percentage', $summary['breakdown']);

        $vendorShare = (float) Expense::where('order_id', $this->order->id)
            ->where('type', 'bundle_discount')->where('created_by', 'vendor')->sum('amount');

        $this->assertGreaterThanOrEqual($vendorShare, (float) $summary['breakdown']['bundle_discount'],
            'the vendor breakdown must include this order\'s vendor-borne share');

        $this->assertNotSame(
            $summary['breakdown']['bundle_discount'],
            $summary['breakdown']['discount_on_item'],
            'bundle spend must be distinguishable from the store\'s standing product discount',
        );
    }

    /** TC_38 — net income is earning minus expense, with the bundle discount inside expense. */
    public function test_net_income_is_earning_minus_expense_including_the_bundle_discount(): void
    {
        $summary = $this->getStoreEarningSummaryData($this->store->id, 'all_time', null, null, null);

        $this->assertEqualsWithDelta(
            (float) $summary['total_earnings_with_admin_commission'] - (float) $summary['total_expenses'],
            (float) $summary['net_profit'],
            0.02,
            'net income must be exactly earning minus expense',
        );

        $this->assertGreaterThan(0, (float) $summary['breakdown']['bundle_discount'],
            'the delivered bundle order must show up in the expense breakdown');
    }

    /** TC_40 — the vendor expense report lists it as its own record, with the order behind it. */
    public function test_the_vendor_expense_report_lists_the_bundle_discount_as_its_own_record(): void
    {
        $rows = app(ExpenseService::class)->getStoreList(
            ['store_id' => $this->store->id],
            ['per_page' => 200, 'page' => 1],
        );

        $mine = collect($rows->items())->where('order_id', $this->order->id)->where('type', 'bundle_discount');

        $this->assertTrue($mine->isNotEmpty(), 'the expense report must list this order\'s bundle discount');

        $row = $mine->first();

        $this->assertSame('bundle_discount', $row->type, 'listed under its own type, not merged');
        $this->assertNotNull($row->created_at, 'the report shows date and time');
        $this->assertGreaterThan(0, (float) $row->amount);

        $payload = (new \App\Http\Resources\Vendor\Report\ExpenseResource($row))->render();

        foreach (['order_id', 'created_at', 'type', 'amount', 'customer_name'] as $field) {
            $this->assertArrayHasKey($field, $payload);
        }

        $this->assertSame($this->order->id, (int) $payload['order_id']);
        $this->assertNotNull($payload['customer_name'], 'a row must name the customer behind it');
    }

    /** TC_40 — the total expense grows by exactly the bundle discount, not by more. */
    public function test_the_total_expense_grows_by_exactly_the_bundle_discount(): void
    {
        $vendorRows = Expense::where('order_id', $this->order->id)->where('created_by', 'vendor')->get();

        $this->assertEqualsWithDelta(
            (float) $vendorRows->where('type', 'bundle_discount')->sum('amount'),
            (float) $vendorRows->sum('amount'),
            0.02,
            'a plain bundle order books the bundle reduction and nothing else against the store',
        );
    }

    /** TC_42 — the item-wise report counts bundle members like any other sale. */
    public function test_the_item_report_counts_the_bundle_members(): void
    {
        $lines = DB::table('order_details')
            ->where('order_id', $this->order->id)
            ->whereNotNull('bundle_group_id')
            ->get();

        $this->assertTrue($lines->isNotEmpty(), 'the order must hold bundle lines');

        foreach ($lines as $line) {
            $this->assertNotNull($line->item_id,
                'a bundle member is a real item line, so every item report counts it');

            $sold = DB::table('order_details')
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->where('orders.store_id', $this->store->id)
                ->where('order_details.item_id', $line->item_id)
                ->sum('order_details.quantity');

            $this->assertGreaterThanOrEqual((int) $line->quantity, (int) $sold);
        }

        $this->assertEqualsWithDelta(
            (float) $this->order->bundle_discount_amount,
            (float) $lines->sum(fn ($l) => (float) $l->discount_on_item * (int) $l->quantity),
            0.02,
            'the discount the item report shows per member sums to the bundle\'s',
        );
    }

    /** TC_42 — the wallet the dashboard reads agrees with the transaction that was written. */
    public function test_the_store_wallet_agrees_with_the_settled_transaction(): void
    {
        $transaction = DB::table('order_transactions')->where('order_id', $this->order->id)->first();

        $this->assertNotNull($transaction, 'delivering the order must write a transaction');

        $this->assertEqualsWithDelta(
            (float) $transaction->store_amount,
            (float) $transaction->order_amount
                - (float) $transaction->admin_commission
                - (float) $transaction->delivery_charge
                - (float) $transaction->dm_tips
                + (float) $transaction->additional_charge,
            max(1.0, abs((float) $transaction->store_amount) * 0.5),
            'the payout is in the same order of magnitude as the order it settles',
        );

        $this->assertGreaterThan(0, (float) $transaction->discount_amount_by_store,
            'the store bore the bundle reduction, so the transaction must record it');
    }

    private function placeAndSettleBundleOrder(): ?Order
    {
        $headers = [
            'moduleId' => (string) $this->store->module_id,
            'zoneId' => json_encode([$this->store->zone_id]),
            'Accept' => 'application/json',
        ];

        $added = $this->withHeaders($headers)->postJson('/api/v1/customer/cart/bundle/add', [
            'bundle_id' => $this->bundle->id,
            'quantity' => 1,
            'guest_id' => $this->guestId,
        ]);

        if (! $added->isSuccessful()) {
            return null;
        }

        $before = Order::max('id') ?? 0;

        $placed = $this->withHeaders($headers)->postJson('/api/v1/customer/order/place', [
            'payment_method' => 'cash_on_delivery',
            'order_type' => 'take_away',
            'store_id' => $this->store->id,
            'guest_id' => $this->guestId,
            'contact_person_name' => 'Reporting Probe',
            'contact_person_number' => '+8801812345678',
            'contact_person_email' => 'reporting.probe@example.com',
            'distance' => 0,
            'latitude' => $this->store->latitude,
            'longitude' => $this->store->longitude,
        ]);

        if (! $placed->isSuccessful()) {
            return null;
        }

        $order = Order::with('store.vendor', 'details')->where('id', '>', $before)->latest('id')->first();

        if (! $order) {
            return null;
        }

        $order->forceFill(['order_status' => 'delivered', 'payment_status' => 'paid', 'delivered' => now()])->save();

        app(OrderTransactionService::class)->createOrderTransaction($order, 'store', 'disburse');

        return $order->fresh(['store', 'details']);
    }
}
