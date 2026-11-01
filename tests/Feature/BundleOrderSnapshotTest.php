<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\Store;
use App\Services\Promotion\BundleOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * What a placed order remembers about the bundle it bought.
 *
 * Two things were read live and should not have been: the bundle's NAME, which a later rename
 * rewrote on every past order, and the expense TYPE, which filed bundle spend under the same
 * label as an ordinary product discount so no report could tell them apart.
 */
class BundleOrderSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_order_line_carries_a_column_for_the_bundle_name(): void
    {
        $this->assertTrue(
            Schema::hasColumn('order_details', 'bundle_name'),
            'The bundle name has to live on the order line: bundle_items already freezes its MEMBER '
            .'names, and without the same treatment for the bundle itself a rename rewrote history.'
        );
    }

    public function test_the_name_is_frozen_onto_the_line_when_the_order_is_placed(): void
    {
        $store = Store::withoutGlobalScopes()->first();

        if (! $store) {
            $this->markTestSkipped('No store seeded.');
        }

        $bundle = Bundle::create([
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'name' => 'Name At Purchase Time',
            'description' => 'x',
            'image' => 'x.png',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'base_price' => 100,
            'discount_percentage' => 10,
            'discounted_price' => 90,
            'status' => 1,
            'created_by' => 'admin',
        ]);

        $columns = app(BundleOrderService::class)->detailColumns([
            'bundle_id' => $bundle->id,
            'bundle_group_id' => 'group-1',
        ]);

        $this->assertSame('Name At Purchase Time', $columns['bundle_name']);

        // The whole point: renaming afterwards must not reach back into what was already written.
        $bundle->update(['name' => 'Renamed Later']);

        $this->assertSame(
            'Name At Purchase Time',
            $columns['bundle_name'],
            'A rename must not change what an order already recorded.'
        );
    }

    public function test_an_ordinary_line_gets_no_bundle_name(): void
    {
        $columns = app(BundleOrderService::class)->detailColumns([
            'bundle_id' => null,
            'bundle_group_id' => null,
        ]);

        $this->assertNull($columns['bundle_name']);
        $this->assertNull($columns['bundle_id']);
    }

    public function test_the_displayed_group_prefers_the_frozen_name_over_the_live_one(): void
    {
        $store = Store::withoutGlobalScopes()->first();

        if (! $store) {
            $this->markTestSkipped('No store seeded.');
        }

        $bundle = Bundle::create([
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'name' => 'Renamed Since',
            'description' => 'x',
            'image' => 'x.png',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'base_price' => 100,
            'discount_percentage' => 10,
            'discounted_price' => 90,
            'status' => 1,
            'created_by' => 'admin',
        ]);

        $line = (object) [
            'bundle_group_id' => 'group-1',
            'bundle_id' => $bundle->id,
            'bundle_name' => 'What The Customer Bought',
            'quantity' => 1,
            'price' => 90.0,
            'discount_on_item' => 10.0,
            'item_id' => 1,
            'item_details' => json_encode(['name' => 'A member']),
            'item' => null,
            'variation' => json_encode([]),
            'add_on_ids' => json_encode([]),
            'add_on_qtys' => json_encode([]),
        ];

        $groups = app(BundleOrderService::class)->orderGroups([$line]);

        $this->assertSame('What The Customer Bought', $groups['bundles'][0]['name']);
    }

    public function test_a_line_written_before_the_column_existed_still_names_itself(): void
    {
        $store = Store::withoutGlobalScopes()->first();

        if (! $store) {
            $this->markTestSkipped('No store seeded.');
        }

        $bundle = Bundle::create([
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'name' => 'Live Fallback Name',
            'description' => 'x',
            'image' => 'x.png',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'base_price' => 100,
            'discount_percentage' => 10,
            'discounted_price' => 90,
            'status' => 1,
            'created_by' => 'admin',
        ]);

        // Every order placed before the migration has a null here and must keep behaving as it did.
        $line = (object) [
            'bundle_group_id' => 'group-1',
            'bundle_id' => $bundle->id,
            'bundle_name' => null,
            'quantity' => 1,
            'price' => 90.0,
            'discount_on_item' => 10.0,
            'item_id' => 1,
            'item_details' => json_encode(['name' => 'A member']),
            'item' => null,
            'variation' => json_encode([]),
            'add_on_ids' => json_encode([]),
            'add_on_qtys' => json_encode([]),
        ];

        $groups = app(BundleOrderService::class)->orderGroups([$line]);

        $this->assertSame('Live Fallback Name', $groups['bundles'][0]['name']);
    }

    public function test_the_expense_report_can_tell_bundle_spend_apart(): void
    {
        $source = file_get_contents(base_path('app/Traits/Order/OrderTransactionsTrait.php'));

        $this->assertStringContainsString(
            "'bundle_discount'",
            $source,
            'A bundle reduction needs its own expense type, as happy hour and BOGO already have. '
            .'Filed under discount_on_product it cannot be filtered, totalled or costed.'
        );

        $report = file_get_contents(base_path('app/Traits/Report/ReportGeneratorTrait.php'));

        $this->assertStringContainsString('bundle_discount', $report);
        $this->assertStringContainsString(
            "'bundle_discount']",
            $report,
            'The expense row has to name the store rather than defaulting to Admin, like its siblings.'
        );
    }

    public function test_the_bundle_expense_split_is_unchanged_by_the_relabelling(): void
    {
        $source = file_get_contents(base_path('app/Traits/Order/OrderTransactionsTrait.php'));

        // The commission-rated split is a commercial decision. Relabelling the rows must not have
        // quietly moved who pays for a bundle.
        $this->assertStringContainsString(
            '$amount_admin = $comission ? ($order->store_discount_amount / 100) * $comission : 0;',
            $source
        );
        $this->assertStringContainsString(
            '$store_d_amount = $order->store_discount_amount - $amount_admin;',
            $source
        );
    }

    /**
     * TC_35 changed this: a member price change now RE-PRICES every bundle holding it, rather
     * than leaving the bundle selling at its old total behind a warning badge. The badge stays
     * for the rows the sync cannot reach -- a line whose item was written straight through the
     * query builder, which fires no model events (see BundleService::repriceForItems()).
     */
    public function test_a_member_price_change_reprices_the_bundle_rather_than_flagging_it(): void
    {
        $store = Store::withoutGlobalScopes()->first();
        $item = \App\Models\Item::withoutGlobalScopes()->where('store_id', $store?->id)->first();

        if (! $store || ! $item) {
            $this->markTestSkipped('No store or item seeded.');
        }

        $bundle = Bundle::create([
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'name' => 'Stale Probe',
            'description' => 'x',
            'image' => 'x.png',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'base_price' => $item->price,
            'discount_percentage' => 10,
            'discounted_price' => $item->price * 0.9,
            'status' => 1,
            'created_by' => 'admin',
        ]);

        \App\Models\BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'item_name' => $item->getRawOriginal('name'),
            'item_image' => $item->image,
            'unit_price' => $item->price,
            'item_price' => $item->price,
        ]);

        $fresh = fn () => Bundle::with(['items', 'items.item:id,price'])->find($bundle->id);

        $this->assertFalse($fresh()->has_stale_pricing, 'nothing has moved yet');

        $original = $item->price;
        $item->update(['price' => $original + 25]);

        $this->assertFalse(
            $fresh()->has_stale_pricing,
            'the bundle was re-priced with the item, so nothing is stale'
        );

        $this->assertEqualsWithDelta(
            (float) $original + 25,
            (float) $fresh()->base_price,
            0.01,
            'the bundle base price follows its only member'
        );

        $item->update(['price' => $original]);

        $this->assertEqualsWithDelta((float) $original, (float) $fresh()->base_price, 0.01,
            'and follows it back down again');

        \Illuminate\Support\Facades\DB::table('items')->where('id', $item->id)->update(['price' => $original + 40]);

        $this->assertTrue($fresh()->has_stale_pricing,
            'a price written straight to the table fires no hook, so the badge is what catches it');
    }

    public function test_a_line_with_no_frozen_item_price_is_not_called_stale(): void
    {
        $store = Store::withoutGlobalScopes()->first();
        $item = \App\Models\Item::withoutGlobalScopes()->where('store_id', $store?->id)->first();

        if (! $store || ! $item) {
            $this->markTestSkipped('No store or item seeded.');
        }

        $bundle = Bundle::create([
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'name' => 'Legacy Probe',
            'description' => 'x',
            'image' => 'x.png',
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
            'base_price' => 10,
            'discount_percentage' => 10,
            'discounted_price' => 9,
            'status' => 1,
            'created_by' => 'admin',
        ]);

        // A row written before item_price existed: unknown is not the same as stale, and badging
        // every pre-existing bundle would make the badge worthless.
        \App\Models\BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'item_name' => $item->getRawOriginal('name'),
            'item_image' => $item->image,
            'unit_price' => 1,
            'item_price' => null,
        ]);

        $this->assertFalse(
            Bundle::with(['items', 'items.item:id,price'])->find($bundle->id)->has_stale_pricing
        );
    }

    public function test_the_picker_marks_what_cannot_currently_sell_without_hiding_it(): void
    {
        $picker = file_get_contents(base_path('app/Traits/Promotion/ProvidesStoreItemPicker.php'));

        // Marked, not filtered: an admin may be building around stock that lands tomorrow, and the
        // same picker serves BOGO, so filtering here would silently change that feature too.
        $this->assertStringContainsString("'is_active' =>", $picker);
        $this->assertStringContainsString("'out_of_stock' =>", $picker);
        $this->assertStringNotContainsString("->where('status', 1)", $picker,
            'the picker must not start filtering: the decision was to mark unsellable items, not hide them');

        $scripts = file_get_contents(resource_path('views/partials/bundle/_picker_scripts.blade.php'));
        $this->assertStringContainsString('unsellableNote', $scripts);
    }
}
