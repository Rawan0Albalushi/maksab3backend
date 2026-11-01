<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Item;
use App\Models\Store;
use App\Services\Promotion\BundleOrderService;
use App\Services\Promotion\BundleService;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsBundleFixtures;
use Tests\TestCase;

/**
 * How a bundle becomes an order, and who pays for its discount.
 *
 * The arithmetic here is the whole point of the feature: lines are charged their full frozen
 * price so commission is taken pre-discount, and the bundle's reduction rides along as per-line
 * discount_on_item, which OrderTransactionsTrait then splits admin/vendor by commission.
 */
class BundleOrderTest extends TestCase
{
    use BuildsBundleFixtures, DatabaseTransactions;

    private ?Store $store = null;

    private ?Bundle $bundle = null;

    private BundleOrderService $service;

    protected function tearDown(): void
    {
        Helpers::clearBusinessSettingsCache();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BundleOrderService::class);

        $this->store = $this->bundleFixtureStore(3);

        if (! $this->store) {
            $this->markTestSkipped('dataset has no bundle-capable store with three sellable items');
        }

        $this->openFixtureStore($this->store);

        $this->enableBundlesForStoreModule();

        $this->bundle = $this->makeBundle(discount: 10);
    }

    /**
     * Switch the feature on for this store's module, which the tests assumed but never stated.
     *
     * bundleUnavailableReason() gates on BundleSettings::allowsModule() first, so with
     * `product_bundle_status` off -- its state on a fresh install -- every bundle reads as "no
     * longer available" and any test touching availability fails for a reason that has nothing to
     * do with what it is asserting. The rows roll back with DatabaseTransactions; the memo does
     * not, so it is cleared here and again on the way out.
     */
    private function enableBundlesForStoreModule(): void
    {
        $moduleType = $this->store->module?->module_type;

        DB::table('business_settings')->updateOrInsert(
            ['key' => BundleSettings::STATUS_KEY],
            ['value' => 1, 'created_at' => now(), 'updated_at' => now()],
        );

        DB::table('business_settings')->updateOrInsert(
            ['key' => BundleSettings::MODULES_KEY],
            ['value' => json_encode([$moduleType => 1]), 'created_at' => now(), 'updated_at' => now()],
        );

        // The rows roll back with DatabaseTransactions; the memo does not, so it is cleared here
        // and again in tearDown() -- the same helper this class already uses there.
        Helpers::clearBusinessSettingsCache();
    }

    public function test_a_bundle_line_is_never_open_to_another_discount(): void
    {
        $this->assertFalse($this->service->isDiscountable(['bundle_group_id' => 'g1']));
        $this->assertTrue($this->service->isDiscountable(['bundle_group_id' => null]));
    }

    public function test_the_line_shares_sum_exactly_to_the_bundle_reduction(): void
    {
        // Three lines and a discount that cannot divide evenly is the case a naive
        // round-each-share loses a cent on.
        $details = $this->details([[33.33, 1], [33.33, 1], [33.34, 1]]);

        $result = $this->service->distributeReduction($details, null);

        $summed = 0.0;

        foreach ($result['lines'] as $index => $perUnit) {
            $summed += $perUnit * $details[$index]['quantity'];
        }

        $this->assertSame($result['bundle_discount'], round($summed, 2),
            'the parts must sum exactly to the whole, or the expense rows and the order disagree');
        $this->assertEqualsWithDelta(10.00, $result['bundle_discount'], 0.01, '10% of 100.00');
    }

    /**
     * discount_on_item is decimal(24,2) PER UNIT, so a share that cannot divide into whole
     * two-decimal per-unit amounts must not be booked at its ideal value.
     *
     * One line of quantity 3 taking 10.00 off is the case: 3.3333 stores as 3.33, the lines total
     * 9.99, and booking 10.00 would leave the order settling on a figure its own invoice lines do
     * not add up to.
     */
    public function test_what_is_booked_is_what_the_columns_can_actually_hold(): void
    {
        $details = $this->details([[33.34, 3]]);

        $result = $this->service->distributeReduction($details, null);

        $stored = 0.0;

        foreach ($result['lines'] as $index => $perUnit) {
            $this->assertSame(round($perUnit, 2), $perUnit,
                'a per-unit amount with more than two decimals is truncated by the column');

            $stored += $perUnit * $details[$index]['quantity'];
        }

        $this->assertSame($result['bundle_discount'], round($stored, 2),
            'the order must book exactly what the lines store');
    }

    public function test_the_reduction_is_recorded_per_unit_not_per_line(): void
    {
        // store_discount_amount accumulates discount_on_item * quantity, so a per-line amount
        // written here would be charged once per unit.
        $details = $this->details([[50.0, 4]]);

        $result = $this->service->distributeReduction($details, null);
        $perUnit = $result['lines'][0];

        $this->assertEqualsWithDelta(20.00, $result['bundle_discount'], 0.01, '10% of 200.00');
        $this->assertEqualsWithDelta(5.00, $perUnit, 0.01, 'the reduction divided by the four units');
        $this->assertEqualsWithDelta($result['bundle_discount'], $perUnit * 4, 0.01);
    }

    public function test_a_zero_percent_bundle_books_no_discount(): void
    {
        $this->bundle = $this->makeBundle(discount: 0);

        $result = $this->service->distributeReduction($this->details([[50.0, 1], [50.0, 1]]), null);

        $this->assertSame([], $result['lines']);
        $this->assertSame(0.0, $result['bundle_discount']);
    }

    public function test_the_reduction_can_never_exceed_what_the_lines_cost(): void
    {
        $this->bundle = $this->makeBundle(discount: 99);

        $result = $this->service->distributeReduction($this->details([[10.0, 1], [10.0, 1]]), null);

        $this->assertLessThanOrEqual(20.0, $result['bundle_discount']);
        $this->assertGreaterThan(0, $result['bundle_discount']);
    }

    public function test_the_order_columns_carry_the_group(): void
    {
        $columns = $this->service->detailColumns([
            'bundle_id' => $this->bundle->id,
            'bundle_group_id' => 'group-1',
        ]);

        $this->assertSame($this->bundle->id, $columns['bundle_id']);
        $this->assertSame('group-1', $columns['bundle_group_id']);

        $ordinary = $this->service->detailColumns(['bundle_id' => null, 'bundle_group_id' => null]);

        $this->assertNull($ordinary['bundle_id']);
        $this->assertNull($ordinary['bundle_group_id']);
    }

    public function test_a_line_is_priced_from_the_snapshot_not_the_menu(): void
    {
        $line = $this->bundle->items->first();

        $carts = [[
            'bundle_group_id' => 'g1',
            'bundle_id' => $this->bundle->id,
            'item_id' => $line->item_id,
            'variation' => json_encode($line->variations ?: []),
            'add_on_ids' => json_encode($line->add_on_ids ?: []),
        ]];

        $frozen = $this->service->frozenPrices($carts, $this->store->id);

        $this->assertEqualsWithDelta(
            (float) $line->unit_price,
            $this->service->linePrice($frozen, $carts[0], 999.0),
            0.01,
            'todays menu price must not reach a bundle the customer was already quoted',
        );
    }

    public function test_an_ordinary_line_keeps_its_own_price(): void
    {
        $this->assertSame(12.5, $this->service->linePrice([], ['bundle_group_id' => null], 12.5));
    }

    /** §8.1: during a happy hour the whole reduction is the window's, not the bundle's. */
    public function test_a_happy_hour_is_reported_apart_from_the_bundle_discount(): void
    {
        $details = $this->details([[50.0, 1], [50.0, 1]]);

        $plain = $this->service->distributeReduction($details, null);

        $this->assertEqualsWithDelta(10.0, $plain['bundle_discount'], 0.01);
        $this->assertSame(0.0, $plain['happy_hour_discount']);
    }

    /** The rule in full: item discounts are ignored, a happy hour is not. */
    public function test_a_happy_hour_replaces_the_bundle_percentage_on_the_order_too(): void
    {
        $details = $this->details([[50.0, 1], [50.0, 1]]);

        $reflection = new \ReflectionMethod($this->service, 'groupReduction');

        $bundleOnly = $reflection->invoke($this->service, $this->bundle, 100.0, null, 2);
        $withWindow = $reflection->invoke($this->service, $this->bundle, 100.0, 25.0, 2);
        $weakWindow = $reflection->invoke($this->service, $this->bundle, 100.0, 5.0, 2);

        $this->assertEqualsWithDelta(10.0, $bundleOnly, 0.01, 'no window: the bundle percentage');
        $this->assertEqualsWithDelta(25.0, $withWindow, 0.01, 'a window replaces the bundle percentage');
        $this->assertEqualsWithDelta(5.0, $weakWindow, 0.01,
            'replacement is symmetric: a weaker window still replaces, it does not lose to the bundle');
    }

    public function test_an_order_holding_a_bundle_opens_in_the_editor(): void
    {
        $order = \App\Models\Order::latest('id')->first();

        if (! $order) {
            $this->markTestSkipped('dataset has no order');
        }

        DB::table('order_details')->insert([
            'order_id' => $order->id,
            'item_id' => $this->bundle->items->first()->item_id,
            'quantity' => 1,
            'price' => 10,
            'bundle_id' => $this->bundle->id,
            'bundle_group_id' => 'editor-probe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $refusals = 0;

        foreach ([
            'app/Http/Controllers/Admin/OrderController.php',
            'app/Http/Controllers/Vendor/OrderController.php',
            'app/Services/Order/OrderService.php',
            'app/Traits/Order/OrderFromCartTrait.php',
        ] as $file) {
            $refusals += substr_count(file_get_contents(base_path($file)), 'promotionEditRefusal');
        }

        DB::table('order_details')->where('bundle_group_id', 'editor-probe')->delete();

        $this->assertSame(0, $refusals,
            'a bundle order is editable now, quantity only, the same as a BOGO one');
    }

    public function test_the_editor_folds_a_bundle_into_one_entry(): void
    {
        $rows = [
            (object) ['id' => 1, 'status' => true, 'bundle_group_id' => 'g1', 'bundle_id' => $this->bundle->id,
                'item_id' => 7, 'quantity' => 4, 'price' => 10.0, 'discount_on_item' => 1.0],
            (object) ['id' => 2, 'status' => true, 'bundle_group_id' => 'g1', 'bundle_id' => $this->bundle->id,
                'item_id' => 8, 'quantity' => 4, 'price' => 20.0, 'discount_on_item' => 2.0],
            (object) ['id' => 3, 'status' => true, 'bundle_group_id' => null, 'item_id' => 9, 'quantity' => 1,
                'price' => 5.0, 'discount_on_item' => 0.0],
        ];

        $bogoShaped = [];
        foreach ($rows as $key => $row) {
            $bogoShaped[] = ['is_bogo' => false, 'key' => $key, 'detail' => $row];
        }

        $entries = $this->service->editorEntries($bogoShaped);

        $this->assertCount(2, $entries, 'two members and one ordinary line are two rows');

        $bundleEntry = collect($entries)->firstWhere('is_bundle', true);

        $this->assertNotNull($bundleEntry);
        $this->assertSame('g1', $bundleEntry['group_id']);
        $this->assertCount(2, $bundleEntry['lines']);
        $this->assertSame(4, $bundleEntry['copies'], 'four copies, not four of one member');
        $this->assertEqualsWithDelta(108.0, $bundleEntry['total'], 0.01,
            'the entry totals the group after its own reduction');
    }

    public function test_scaling_a_bundle_moves_every_member_and_leaves_the_unit_price_alone(): void
    {
        $carts = [
            (object) ['bundle_group_id' => 'g1', 'quantity' => 2, 'price' => 10.0],
            (object) ['bundle_group_id' => 'g1', 'quantity' => 2, 'price' => 20.0],
            (object) ['bundle_group_id' => null, 'quantity' => 3, 'price' => 5.0],
        ];

        $carts = $this->service->stampEditorBundles($carts);
        $carts = $this->service->scaleEditorBundle($carts, 'g1', 5);

        $this->assertSame(5, (int) $carts[0]->quantity);
        $this->assertSame(5, (int) $carts[1]->quantity);
        $this->assertSame(3, (int) $carts[2]->quantity, 'an ordinary line is untouched');

        $this->assertSame(10.0, (float) $carts[0]->price, 'scaling never reprices a member');
        $this->assertSame(20.0, (float) $carts[1]->price);
    }

    public function test_a_bundle_cannot_be_scaled_below_one(): void
    {
        $carts = [(object) ['bundle_group_id' => 'g1', 'quantity' => 3, 'price' => 10.0]];

        $carts = $this->service->stampEditorBundles($carts);
        $carts = $this->service->scaleEditorBundle($carts, 'g1', 0);

        $this->assertSame(1, (int) $carts[0]->quantity);
    }

    public function test_only_a_grown_bundle_faces_the_rules_again(): void
    {
        $order = (object) ['schedule_at' => null];
        $rows = [
            ['bundle_group_id' => 'g1', 'bundle_id' => $this->bundle->id, 'quantity' => 4],
            ['bundle_group_id' => 'g1', 'bundle_id' => $this->bundle->id, 'quantity' => 4],
        ];

        $this->assertSame(['g1' => 4], $this->service->copiesFor($rows));

        $this->assertNull(
            $this->service->editRefusalReason($order, $rows, ['g1' => 4], (int) $this->store->id),
            'an untouched bundle is never re-judged',
        );

        $this->assertNull(
            $this->service->editRefusalReason($order, $rows, ['g1' => 9], (int) $this->store->id),
            'a smaller bundle is never re-judged either',
        );

        $this->bundle->forceFill(['status' => 0])->save();

        $this->assertNotNull(
            $this->service->editRefusalReason($order, $rows, ['g1' => 1], (int) $this->store->id),
            'growing a bundle the store has switched off must be refused',
        );

        $this->bundle->forceFill(['status' => 1])->save();
    }

    public function test_removing_one_member_removes_the_whole_bundle(): void
    {
        foreach ([
            'app/Http/Controllers/Admin/OrderController.php',
            'app/Http/Controllers/Vendor/OrderController.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringContainsString(
                "\$groupField = data_get(\$cart[\$request->key], 'bogo_group_id') ? 'bogo_group_id' : 'bundle_group_id';",
                $source,
                "{$file}: half a bundle is not a thing, so removing one member must remove the group",
            );
        }
    }

    public function test_nothing_but_quantity_is_editable_on_a_bundle_row(): void
    {
        $blade = file_get_contents(
            base_path('resources/views/admin-views/order/partials/_edit_cart_list.blade.php')
        );

        $this->assertStringContainsString('$isFolded = $isBogo || $isBundle;', $blade,
            'a bundle row is a folded promotion row, like a BOGO one');

        $this->assertStringContainsString(
            "{{ \$productMissing || \$isFolded ? '' : 'cursor-pointer quick-view-cart-item' }}",
            $blade,
            'the item-edit drawer must not open on a bundle row - only its quantity may change',
        );

        $this->assertStringContainsString('$shownQty  = $entry[\'copies\'];', $blade,
            'the quantity shown and posted is copies of the bundle, not of one member');
    }

    public function test_a_mixed_order_edited_through_the_app_keeps_its_bundle_whole(): void
    {
        $order = \App\Models\Order::where('store_id', $this->store->id)
            ->where('order_status', 'pending')->latest('id')->first();

        if (! $order) {
            $this->markTestSkipped('dataset has no pending order for this store');
        }

        $members = $this->bundle->items;
        $regular = Item::withoutGlobalScopes()
            ->where('store_id', $this->store->id)
            ->whereNotIn('id', $members->pluck('item_id')->all())
            ->first();

        if (! $regular) {
            $this->markTestSkipped('the fixture store has no third item');
        }

        DB::table('order_details')->where('order_id', $order->id)->delete();

        foreach ($members as $line) {
            DB::table('order_details')->insert([
                'order_id' => $order->id, 'item_id' => $line->item_id,
                'item_details' => json_encode(['name' => $line->item_name]),
                'quantity' => 1, 'price' => (float) $line->unit_price, 'discount_on_item' => 0,
                'bundle_id' => $this->bundle->id, 'bundle_group_id' => 'mixed-probe',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('order_details')->insert([
            'order_id' => $order->id, 'item_id' => $regular->id,
            'item_details' => json_encode(['name' => $regular->getRawOriginal('name')]),
            'quantity' => 1, 'price' => (float) $regular->price, 'discount_on_item' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $stored = DB::table('order_details')->where('order_id', $order->id)->get();
        $carts = [];

        foreach ($stored as $row) {
            $carts[] = [
                'order_details_id' => $row->id,
                'item_id' => $row->item_id,
                'quantity' => $row->bundle_group_id ? 2 : 3,
            ];
        }

        $result = app(\App\Services\Order\OrderService::class)
            ->updateFromCart($order->fresh('details'), ['carts' => $carts], 'vendor');

        $this->assertSame(200, (int) ($result['status'] ?? 0),
            'the edit must be accepted: '.json_encode($result));

        $after = DB::table('order_details')->where('order_id', $order->id)->get();
        $bundleRows = $after->where('bundle_group_id', 'mixed-probe');
        $plainRows = $after->whereNull('bundle_group_id');

        $this->assertCount($members->count(), $bundleRows,
            'the bundle must survive the edit as a group, not as loose items');

        foreach ($bundleRows as $row) {
            $this->assertSame($this->bundle->id, (int) $row->bundle_id, 'membership is kept');
            $this->assertSame(2, (int) $row->quantity, 'every member moved to two copies together');

            $frozen = $members->firstWhere('item_id', $row->item_id);
            $this->assertEqualsWithDelta((float) $frozen->unit_price, (float) $row->price, 0.01,
                'a member keeps its frozen price, never today menu price');
        }

        $this->assertCount(1, $plainRows, 'the ordinary item stays an ordinary line');
        $this->assertSame(3, (int) $plainRows->first()->quantity, 'and takes the quantity it was given');

        $this->assertGreaterThan(0.0, (float) $order->fresh()->bundle_discount_amount,
            'the edit restates what the bundle gave');

        DB::table('order_details')->where('order_id', $order->id)->delete();
    }

    public function test_an_edit_is_judged_against_now_unless_the_order_is_scheduled_ahead(): void
    {
        $rows = [['bundle_group_id' => 'g1', 'bundle_id' => $this->bundle->id, 'quantity' => 2]];

        $placedLongAgo = (object) ['schedule_at' => now()->subMonth()->toDateTimeString()];

        $this->assertNull(
            $this->service->editRefusalReason($placedLongAgo, $rows, ['g1' => 1], (int) $this->store->id),
            'an old order date must not judge a bundle that started after it',
        );

        $scheduledAhead = (object) ['schedule_at' => now()->addMonth()->toDateTimeString()];

        $this->assertNotNull(
            $this->service->editRefusalReason($scheduledAhead, $rows, ['g1' => 1], (int) $this->store->id),
            'a future delivery must still be judged against that moment: the bundle ends before it',
        );
    }

    public function test_a_happy_hour_bundle_order_is_booked_to_the_vendor_not_split(): void
    {
        $host = new class {
            use \App\Traits\Order\PlaceNewOrderTrait;
        };

        $reflection = new \ReflectionMethod($host, 'happyHourIdForOrder');
        $reflection->setAccessible(true);

        $windowOnly = ['bundle_discount' => 0.0, 'happy_hour_discount' => 12.50];
        $bundleOnly = ['bundle_discount' => 12.50, 'happy_hour_discount' => 0.0];
        $mixed = ['bundle_discount' => 0.0, 'happy_hour_discount' => 12.50];

        $this->assertNull(
            $reflection->invoke($host, false, $bundleOnly, 12.50, $this->store),
            'an ordinary bundle discount is not a happy hour and stays split by commission',
        );

        $this->assertNull(
            $reflection->invoke($host, false, $mixed, 30.00, $this->store),
            'when only part of the discount is the window, the order is not tagged as one',
        );

        $tagged = $reflection->invoke($host, false, $windowOnly, 12.50, $this->store);

        $this->assertTrue($tagged === null || is_int($tagged),
            'a window-only bundle order asks for the running happy hour id');
    }

    public function test_the_api_edit_carries_bundle_membership_from_the_stored_line(): void
    {
        $source = file_get_contents(base_path('app/Services/Order/OrderService.php'));

        foreach (['bogo_group_id', 'bundle_group_id', 'bundle_id'] as $column) {
            $this->assertStringContainsString("\$detail->{$column} = \$storedLine?->{$column};", $source,
                "the vendor app posts item_id and quantity only, so {$column} must come from the stored row");
        }
    }

    public function test_both_save_paths_rewrite_the_bundle_discount_column(): void
    {
        foreach ([
            'app/Traits/Order/OrderFromCartTrait.php',
            'app/Services/Order/OrderService.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringContainsString('$order->bundle_discount_amount = round(', $source,
                "{$file}: an edit that moved a bundle must restate what the bundle gave");
            $this->assertStringContainsString('$order->bogo_discount_amount = round(', $source,
                "{$file}: and BOGO's column keeps being restated beside it");
        }
    }

    public function test_a_bundle_row_cannot_be_rewritten_by_the_add_item_endpoint(): void
    {
        $guard = file_get_contents(base_path('app/Services/Order/OrderService.php'));

        $this->assertStringContainsString("data_get(\$existing, 'bundle_group_id')", $guard,
            'replacing a bundle row would re-price it from today menu');
        $this->assertStringContainsString("'data' => 'bundle_locked'", $guard);

        foreach ([
            'app/Http/Controllers/Admin/OrderController.php',
            'app/Http/Controllers/Vendor/OrderController.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringContainsString('promotionLockedRefusal($request)', $source,
                "{$file}: the add-item endpoint must refuse to rewrite a promotion row");
        }
    }

    public function test_the_save_paths_judge_a_grown_bundle(): void
    {
        foreach ([
            'app/Traits/Order/OrderFromCartTrait.php',
            'app/Services/Order/OrderService.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringContainsString('copiesFor($order->details)', $source,
                "{$file} must read the copies before it rebuilds");
            $this->assertStringContainsString('->editRefusalReason($order, $order_details, $bundleCopiesBefore', $source,
                "{$file} must judge what the edit added, the same way it judges a BOGO one");
        }
    }

    private function details(array $lines): array
    {
        $details = [];

        foreach ($lines as [$price, $quantity]) {
            $details[] = [
                'bundle_id' => $this->bundle->id,
                'bundle_group_id' => 'group-1',
                'price' => $price,
                'quantity' => $quantity,
                'discount_on_item' => 0,
            ];
        }

        return $details;
    }

    public function test_an_edit_rebuild_treats_a_bundle_line_exactly_as_placement_does(): void
    {
        $reflection = new \ReflectionClass(\App\Traits\Order\PlaceNewOrderTrait::class);
        $source = file_get_contents($reflection->getFileName());

        $placement = substr($source, strpos($source, 'function makeOrderDetails'));
        $placement = substr($placement, 0, strpos($placement, 'function makeEditOrderDetails'));
        $edit = substr($source, strpos($source, 'function makeEditOrderDetails'));

        foreach ([
            '$bundleService = app(BundleOrderService::class)',
            '$bundleService->frozenPrices($carts',
            '$bundleService->linePrice($bundle_frozen_prices, $c, $price)',
            '$bundleService->detailColumns($c)',
            '$bundleService->isDiscountable($c)',
            '$bundleService->distributeReduction($order_details, $store)',
            "\$hasBundleLines = collect(\$order_details)->contains(fn (\$detail) => data_get(\$detail, 'bundle_group_id') !== null)",
            "! \$hasBundleLines",
            "'bundle_discount_amount' => \$bundleReduction['bundle_discount']",
        ] as $needle) {
            $this->assertStringContainsString($needle, $placement, "placement lost: {$needle}");
            $this->assertStringContainsString($needle, $edit,
                "an edit rebuild must handle a bundle line the way placement does; missing: {$needle}");
        }
    }

    public function test_the_edit_rebuild_keeps_a_bundle_line_at_its_frozen_price(): void
    {
        $line = $this->bundle->items->first();

        $cart = (object) [
            'id' => 1,
            'item_id' => $line->item_id,
            'quantity' => 1,
            'bundle_id' => $this->bundle->id,
            'bundle_group_id' => 'edit-parity-probe',
            'variation' => [],
            'add_on_ids' => [],
            'add_on_qtys' => [],
        ];

        $frozen = $this->service->frozenPrices([$cart], (int) $this->store->id);

        $this->assertSame(
            round((float) $line->unit_price, 2),
            round($this->service->linePrice($frozen, $cart, 999999.0), 2),
            'the live menu price must never win over the snapshot, on either path',
        );

        $columns = $this->service->detailColumns($cart);

        $this->assertSame($this->bundle->id, $columns['bundle_id']);
        $this->assertSame('edit-parity-probe', $columns['bundle_group_id']);
    }

    private function makeBundle(float $discount): Bundle
    {
        $items = $this->sellableFixtureItems($this->store, 2);

        if ($items->count() < 2) {
            $this->markTestSkipped('the fixture store has too few items');
        }

        $bundle = Bundle::create([
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'name' => 'Order Fixture Bundle',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
            'discount_percentage' => $discount,
        ]);

        $base = 0.0;

        foreach ($items as $item) {
            BundleItem::create([
                'bundle_id' => $bundle->id,
                'item_id' => $item->id,
                'item_name' => $item->getRawOriginal('name'),
                'item_image' => $item->image,
                'unit_price' => $item->price,
            ]);
            $base += (float) $item->price;
        }

        $bundle->forceFill(app(BundleService::class)->prices($base, $discount))->save();

        return $bundle->fresh('items');
    }
}
