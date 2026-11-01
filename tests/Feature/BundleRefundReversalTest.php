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
use App\Traits\Report\ReportGeneratorTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsBundleFixtures;
use Tests\TestCase;

/**
 * TC_43 — cancelling or refunding a bundle order puts everything back.
 *
 * The earning side already drops a refunded order through Order::scopeNotRefunded(), joined via
 * order_transactions. The expense side has to be dropped too, or the bundle_discount row written
 * at delivery outlives the revenue it was spent against and every "total expense" figure carries
 * it forever. Stock goes back member by member, because a bundle member is an ordinary order line.
 */
class BundleRefundReversalTest extends TestCase
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

        $this->order = $this->placeBundleOrder();

        if (! $this->order) {
            $this->markTestSkipped('the fixture could not place a bundle order in this dataset');
        }
    }

    /** Refunding drops the bundle expense out of the vendor's breakdown. */
    public function test_a_refunded_order_leaves_no_bundle_expense_in_the_breakdown(): void
    {
        $this->deliver();

        $before = (float) $this->breakdown()['bundle_discount'];
        $booked = (float) Expense::where('order_id', $this->order->id)
            ->where('type', 'bundle_discount')->where('created_by', 'vendor')->sum('amount');

        $this->assertGreaterThan(0, $booked, 'the delivered order must have booked a bundle expense');

        $this->refund();

        $this->assertEqualsWithDelta($before - $booked, (float) $this->breakdown()['bundle_discount'], 0.02,
            'a refunded order\'s bundle spend must come back out of the breakdown');
    }

    /** And out of the expense LIST the vendor app reads, not just the summary. */
    public function test_a_refunded_order_leaves_no_bundle_expense_in_the_expense_list(): void
    {
        $this->deliver();
        $this->refund();

        $rows = app(ExpenseService::class)->getStoreList(
            ['store_id' => $this->store->id],
            ['per_page' => 500, 'page' => 1],
        );

        $this->assertEmpty(
            collect($rows->items())->where('order_id', $this->order->id)->all(),
            'the expense report must not list an expense against a refunded order',
        );
    }

    /** TC_43 — the EARNING side drops the refunded order too, not just the expense side. */
    public function test_a_refunded_order_is_dropped_from_the_earning_side(): void
    {
        $this->deliver();

        $before = (float) $this->summary()['total_earnings_with_admin_commission'];
        $settled = (float) DB::table('order_transactions')
            ->where('order_id', $this->order->id)->value('store_amount');

        $this->assertGreaterThan(0, $before, 'the delivered order must have been earned on');

        $this->refund();

        $this->assertLessThan($before, (float) $this->summary()['total_earnings_with_admin_commission'],
            'a refunded order must stop counting as earning');
        $this->assertNotNull($settled);
    }

    /** TC_43 — the transaction is restamped so nothing reads it as a live settlement. */
    public function test_the_transaction_is_restamped_as_refunded(): void
    {
        $this->deliver();
        $this->refund();

        $this->assertStringContainsString(
            'refunded',
            (string) DB::table('order_transactions')->where('order_id', $this->order->id)->value('status'),
        );
    }

    /** TC_43 — the wallets the dashboard reads are wound back by what they were credited. */
    public function test_the_wallets_are_wound_back(): void
    {
        $this->deliver();

        $transaction = DB::table('order_transactions')->where('order_id', $this->order->id)->first();
        $vendorId = $this->store->vendor_id;

        $before = (float) DB::table('store_wallets')->where('vendor_id', $vendorId)->value('total_earning');

        $this->refund();

        $this->assertEqualsWithDelta(
            $before - (float) $transaction->store_amount,
            (float) DB::table('store_wallets')->where('vendor_id', $vendorId)->value('total_earning'),
            0.02,
            'the store wallet gives back exactly what the settlement credited',
        );
    }

    /** TC_43 — a cancelled bundle order leaves no member counted as sold. */
    public function test_cancelling_leaves_the_member_sell_counts_untouched(): void
    {
        $lines = DB::table('order_details')
            ->where('order_id', $this->order->id)->whereNotNull('bundle_group_id')->get();

        $before = Item::withoutGlobalScopes()->whereIn('id', $lines->pluck('item_id'))->pluck('order_count', 'id');

        app(OrderService::class)->cancel(
            Order::with(['module', 'details.item', 'details.campaign', 'store'])->find($this->order->id),
            ['reason' => 'TC_43 probe'],
        );

        $after = Item::withoutGlobalScopes()->whereIn('id', $lines->pluck('item_id'))->pluck('order_count', 'id');

        foreach ($lines as $line) {
            $this->assertSame((int) $before[$line->item_id], (int) $after[$line->item_id],
                'sell counts are credited at delivery, so a cancelled order must never have raised them');
        }
    }

    /** Net income still reconciles after the reversal. */
    public function test_net_income_still_reconciles_after_a_refund(): void
    {
        $this->deliver();
        $this->refund();

        $summary = $this->getStoreEarningSummaryData($this->store->id, 'all_time', null, null, null);

        $this->assertEqualsWithDelta(
            (float) $summary['total_earnings_with_admin_commission'] - (float) $summary['total_expenses'],
            (float) $summary['net_profit'],
            0.02,
        );
    }

    /** Cancelling puts every member's stock back, one line at a time. */
    public function test_cancelling_restores_the_stock_of_every_bundle_member(): void
    {
        if (! config('module.'.$this->store->module?->module_type.'.stock')) {
            $this->markTestSkipped('this module does not keep stock');
        }

        $lines = DB::table('order_details')
            ->where('order_id', $this->order->id)->whereNotNull('bundle_group_id')->get();

        $before = Item::withoutGlobalScopes()
            ->whereIn('id', $lines->pluck('item_id'))->pluck('stock', 'id');

        app(OrderService::class)->cancel(
            Order::with(['module', 'details.item', 'details.campaign', 'store'])->find($this->order->id),
            ['reason' => 'TC_43 probe'],
        );

        $after = Item::withoutGlobalScopes()
            ->whereIn('id', $lines->pluck('item_id'))->pluck('stock', 'id');

        foreach ($lines as $line) {
            $this->assertSame(
                (int) $before[$line->item_id] + (int) $line->quantity,
                (int) $after[$line->item_id],
                'a cancelled bundle member returns its quantity to stock',
            );
        }
    }

    /** A cancelled order books no expense at all — there was nothing to spend. */
    public function test_a_cancelled_order_books_no_bundle_expense(): void
    {
        app(OrderService::class)->cancel(
            Order::with(['module', 'details.item', 'details.campaign', 'store'])->find($this->order->id),
            ['reason' => 'TC_43 probe'],
        );

        $this->assertSame(0, Expense::where('order_id', $this->order->id)->count(),
            'an order that never settled must leave no expense behind');
    }

    private function breakdown(): array
    {
        return $this->summary()['breakdown'];
    }

    private function summary(): array
    {
        return $this->getStoreEarningSummaryData($this->store->id, 'all_time', null, null, null);
    }

    private function deliver(): void
    {
        $order = Order::with('store.vendor', 'details')->find($this->order->id);
        $order->forceFill([
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'delivered' => now(),
        ])->save();

        app(OrderTransactionService::class)->createOrderTransaction($order, 'store', 'disburse');
    }

    private function refund(): void
    {
        $order = Order::with('store.vendor', 'transaction')->find($this->order->id);

        app(OrderTransactionService::class)->refundOrderTransaction($order);

        $order->forceFill(['order_status' => 'refunded', 'refunded' => now()])->save();
    }

    private function placeBundleOrder(): ?Order
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
            'contact_person_name' => 'Refund Probe',
            'contact_person_number' => '+8801812345678',
            'contact_person_email' => 'refund.probe@example.com',
            'distance' => 0,
            'latitude' => $this->store->latitude,
            'longitude' => $this->store->longitude,
        ]);

        return $placed->isSuccessful()
            ? Order::where('id', '>', $before)->latest('id')->first()
            : null;
    }
}
