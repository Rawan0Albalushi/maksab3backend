<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Item;
use App\Models\Store;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BundleSchemaTest extends TestCase
{
    use DatabaseTransactions;

    private function makeBundle(array $overrides = []): Bundle
    {
        $store = Store::withoutGlobalScopes()->first();

        if (! $store) {
            $this->markTestSkipped('No store seeded.');
        }

        return Bundle::create(array_merge([
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'name' => 'ZZ Test Bundle',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(30),
            'base_price' => 310,
            'discount_percentage' => 5,
            'discounted_price' => 294.50,
            'created_by' => 'admin',
        ], $overrides));
    }

    private function addItem(Bundle $bundle, float $unitPrice): BundleItem
    {
        $item = Item::withoutGlobalScopes()->first();

        return BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'item_name' => $item->getRawOriginal('name'),
            'item_image' => $item->image,
            'unit_price' => $unitPrice,
            'variations' => [],
            'add_on_ids' => [],
            'add_on_qtys' => [],
        ]);
    }

    public function test_the_tables_carry_the_agreed_columns(): void
    {
        foreach (['store_id', 'module_id', 'name', 'start_date', 'end_date', 'base_price', 'discount_percentage', 'discounted_price', 'status', 'created_by', 'deleted_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('bundles', $column), "bundles.$column is missing");
        }

        foreach (['bundle_id', 'item_id', 'quantity', 'item_name', 'unit_price', 'variations', 'add_on_ids', 'add_on_qtys'] as $column) {
            $this->assertTrue(Schema::hasColumn('bundle_items', $column), "bundle_items.$column is missing");
        }
    }

    public function test_the_bogo_only_concepts_are_absent(): void
    {
        foreach (['approval_status', 'rejected_by', 'checked', 'combination_signature', 'buy_qty', 'get_qty', 'order_types', 'usage_limit_total'] as $column) {
            $this->assertFalse(Schema::hasColumn('bundles', $column), "bundles.$column should not exist — no approval, no caps, no buy/get");
        }

        $this->assertFalse(Schema::hasColumn('bundle_items', 'type'), 'There is no buy/get side.');
        $this->assertFalse(Schema::hasTable('bundle_usages'), 'There are no usage caps.');
    }

    public function test_the_cart_and_order_tables_can_carry_a_bundle(): void
    {
        $this->assertTrue(Schema::hasColumn('carts', 'bundle_id'));
        $this->assertTrue(Schema::hasColumn('carts', 'bundle_group_id'));
        $this->assertTrue(Schema::hasColumn('order_details', 'bundle_id'));
        $this->assertTrue(Schema::hasColumn('order_details', 'bundle_group_id'));
        $this->assertTrue(Schema::hasColumn('orders', 'bundle_discount_amount'));

        $this->assertFalse(Schema::hasColumn('carts', 'is_free_item_bundle'));
    }

    public function test_the_same_item_may_appear_twice(): void
    {
        $bundle = $this->makeBundle();
        $this->addItem($bundle, 200);
        $this->addItem($bundle, 110);

        $this->assertSame(2, $bundle->items()->count(), 'Duplicates are allowed and are separate rows.');
        $this->assertSame(310.0, (float) $bundle->items()->sum('unit_price'));
    }

    public function test_the_relationships_are_enforced_by_foreign_keys(): void
    {
        $keys = collect(\Illuminate\Support\Facades\DB::select(
            "SELECT table_name, column_name, referenced_table_name
             FROM information_schema.key_column_usage
             WHERE table_schema = DATABASE()
               AND table_name IN ('bundles','bundle_items')
               AND referenced_table_name IS NOT NULL"
        ))->map(fn ($row) => strtolower($row->TABLE_NAME.'.'.$row->COLUMN_NAME.'->'.$row->REFERENCED_TABLE_NAME));

        foreach ([
            'bundles.store_id->stores',
            'bundles.module_id->modules',
            'bundle_items.bundle_id->bundles',
            'bundle_items.item_id->items',
        ] as $expected) {
            $this->assertTrue($keys->contains($expected), "Missing foreign key: {$expected}");
        }

        $this->assertCount(4, $keys, 'Exactly four keys, and none on the core tables.');
    }

    public function test_the_lookup_columns_are_indexed(): void
    {
        $indexed = function (string $table): array {
            return collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM '.$table))
                ->pluck('Key_name')->unique()->values()->all();
        };

        $this->assertContains('bundles_store_id_status_index', $indexed('bundles'));
        $this->assertContains('bundles_module_id_status_index', $indexed('bundles'));
        $this->assertContains('bundles_start_date_end_date_index', $indexed('bundles'));

        $this->assertContains('bundle_items_bundle_id_index', $indexed('bundle_items'));
        $this->assertContains('bundle_items_item_id_index', $indexed('bundle_items'), 'Needed to find which bundles hold an item.');

        $this->assertContains('bundle_items_service_id_index', $indexed('bundle_items'),
            'A service bundle is looked up the same way an item one is.');

        $this->assertSame(
            [
                'PRIMARY',
                'bundle_items_bundle_id_index',
                'bundle_items_item_id_index',
                'bundle_items_service_id_index',
            ],
            $indexed('bundle_items'),
            'One index per column — the foreign key reuses the declared index rather than adding its own.',
        );

        $this->assertContains('carts_bundle_group_id_index', $indexed('carts'));
        $this->assertContains('carts_bundle_id_index', $indexed('carts'));
        $this->assertContains('order_details_bundle_id_index', $indexed('order_details'));
    }

    public function test_force_deleting_a_bundle_removes_its_items(): void
    {
        $bundle = $this->makeBundle();
        $this->addItem($bundle, 100);
        $id = $bundle->id;

        $bundle->forceDelete();

        $this->assertSame(0, BundleItem::where('bundle_id', $id)->count(), 'The foreign key cascade takes them.');
    }

    public function test_force_deleting_a_bundle_leaves_no_orphan_translation_or_storage_rows(): void
    {
        $bundle = $this->makeBundle();

        $bundle->translations()->create([
            'locale' => 'zz',
            'key' => 'name',
            'value' => 'ZZ localised name',
        ]);

        $this->assertSame(1, $bundle->translations()->count());

        $type = $bundle->getMorphClass();
        $id = $bundle->id;

        $bundle->forceDelete();

        $this->assertSame(
            0,
            \Illuminate\Support\Facades\DB::table('translations')
                ->where('translationable_type', $type)->where('translationable_id', $id)->count(),
            'Translations are polymorphic — no foreign key cascade reaches them.',
        );
    }

    public function test_soft_deleting_a_bundle_keeps_its_translations_for_a_restore(): void
    {
        $bundle = $this->makeBundle();
        $bundle->translations()->create(['locale' => 'zz', 'key' => 'name', 'value' => 'ZZ localised name']);

        $bundle->delete();

        $this->assertSame(1, $bundle->translations()->count(), 'A soft delete must stay restorable.');
    }

    public function test_soft_deleting_a_bundle_keeps_its_items_for_a_restore(): void
    {
        $bundle = $this->makeBundle();
        $this->addItem($bundle, 100);

        $bundle->delete();

        $this->assertSame(1, BundleItem::where('bundle_id', $bundle->id)->count(), 'A soft delete must stay restorable.');
        $this->assertTrue($bundle->fresh()->trashed());
    }

    public function test_visibility_follows_the_window_without_touching_status(): void
    {
        $ended = $this->makeBundle(['start_date' => now()->subDays(10), 'end_date' => now()->subDay()]);

        $this->assertSame('ended', $ended->visibilityStatus());
        $this->assertSame(1, $ended->status, 'An expired bundle keeps its status; only visibility changes.');
        $this->assertFalse($ended->isRunning());

        $scheduled = $this->makeBundle(['start_date' => now()->addDay(), 'end_date' => now()->addDays(10)]);
        $this->assertSame('scheduled', $scheduled->visibilityStatus());

        $off = $this->makeBundle(['status' => 0]);
        $this->assertSame('not_visible', $off->visibilityStatus());

        $this->assertSame('running', $this->makeBundle()->visibilityStatus());
    }

    public function test_the_running_scope_matches_the_visibility_rule(): void
    {
        $running = $this->makeBundle();
        $ended = $this->makeBundle(['start_date' => now()->subDays(10), 'end_date' => now()->subDay()]);
        $off = $this->makeBundle(['status' => 0]);

        $ids = Bundle::running()->pluck('id');

        $this->assertTrue($ids->contains($running->id));
        $this->assertFalse($ids->contains($ended->id));
        $this->assertFalse($ids->contains($off->id));
    }

    public function test_the_agreed_limits_are_declared_on_the_model(): void
    {
        $this->assertSame(2, Bundle::MIN_ITEMS);
        $this->assertSame(99, Bundle::MAX_DISCOUNT_PERCENTAGE);
    }

    public function test_json_columns_survive_a_round_trip(): void
    {
        $bundle = $this->makeBundle();
        $item = Item::withoutGlobalScopes()->first();

        $line = BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'item_name' => 'frozen name',
            'unit_price' => 50,
            'variations' => [['name' => 'Size', 'values' => ['label' => ['Half']]]],
            'add_on_ids' => [3, 7],
            'add_on_qtys' => [1, 2],
        ]);

        $line->refresh();

        $this->assertSame([3, 7], $line->add_on_ids);
        $this->assertSame('Half', $line->variations[0]['values']['label'][0]);
        $this->assertSame('frozen name', $line->item_name);
    }
}
