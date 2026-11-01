<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\Vendor;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BundleVendorPanelTest extends TestCase
{
    use DatabaseTransactions;

    private ?Store $store = null;

    private ?Vendor $vendor = null;

    private ?Store $otherStore = null;

    protected function tearDown(): void
    {
        Helpers::clearBusinessSettingsCache();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::withoutGlobalScopes()
            ->whereHas('module', fn ($q) => $q->whereIn('module_type', BundleSettings::moduleTypes()))
            ->whereNotNull('vendor_id')
            ->where('status', 1)
            ->first();

        if (! $this->store) {
            $this->markTestSkipped('dataset has no store in a bundle-capable module');
        }

        $this->vendor = Vendor::find($this->store->vendor_id);

        if (! $this->vendor) {
            $this->markTestSkipped('the fixture store has no vendor');
        }

        $this->otherStore = Store::withoutGlobalScopes()
            ->where('module_id', $this->store->module_id)
            ->where('id', '!=', $this->store->id)
            ->first();

        $this->storeSettings(status: true);
    }

    public function test_the_list_renders_without_a_store_column(): void
    {
        $this->makeBundle($this->store);

        $html = $this->actingAsVendor()->get(route('vendor.bundle.list'))->assertOk()->getContent();

        $this->assertStringContainsString(translate('messages.Bundle Package List'), $html);
        $this->assertStringNotContainsString('<th>'.translate('messages.Store').'</th>', $html,
            'one store, so the column carries no information');
    }

    /** The vendor list is the same partials as the admin one, minus the Store column. */
    public function test_the_vendor_list_matches_the_shared_layout(): void
    {
        $this->makeBundle($this->store);

        $html = $this->actingAsVendor()->get(route('vendor.bundle.list'))->assertOk()->getContent();

        $this->assertStringContainsString('bundleExportDropdown', $html, 'the export belongs here too');
        $this->assertStringContainsString('datatableSearch_', $html);
        $this->assertStringContainsString('table-responsive datatable-custom', $html);

        // The two bugs fixed on the admin list: a horizontal inset that pushed the table off the
        // card edges, and a clipping wrapper that cut the Action column off.
        $this->assertStringNotContainsString('px-3 pb-3', $html, 'no horizontal inset on the table block');
        $this->assertStringNotContainsString('overflow-hidden', $html, 'nothing may clip the last column');

        $this->assertStringNotContainsString('bundle-per-page', $html);
        $this->assertStringContainsString(translate('messages.Action'), $html);
    }

    public function test_the_create_form_locks_the_store(): void
    {
        $html = $this->actingAsVendor()->get(route('vendor.bundle.create'))->assertOk()->getContent();

        $this->assertStringContainsString('name="store_id" id="bundle_store_id" value="'.$this->store->id.'"', $html);
        $this->assertStringNotContainsString('<option value="">'.translate('messages.Select Store').'</option>', $html,
            'the vendor has no store to pick');
    }

    public function test_no_vendor_screen_talks_about_stores(): void
    {
        $bundle = $this->makeBundle($this->store);

        $screens = [
            'list' => $this->actingAsVendor()->get(route('vendor.bundle.list'))->assertOk()->getContent(),
            'create' => $this->actingAsVendor()->get(route('vendor.bundle.create'))->assertOk()->getContent(),
            'edit' => $this->actingAsVendor()->get(route('vendor.bundle.edit', $bundle->id))->assertOk()->getContent(),
            'detail' => $this->actingAsVendor()
                ->get(route('vendor.bundle.view', $bundle->id), ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertOk()->getContent(),
        ];

        foreach ($screens as $page => $html) {
            $body = substr($html, strpos($html, '<body') ?: 0);

            foreach ([
                translate('messages.Select store'),
                translate('messages.Pick a store and products — prices update live, and you can add a discount'),
                translate('messages.Select a store to create your bundle. Choose products to include, and the total price updates in real-time. Enter a discount percentage to see the final price'),
                translate('messages.Search and pick an item from this store'),
                translate('messages.No item found in this store'),
                translate('messages.Pick the store this bundle belongs to Its menu is what the item search offers'),
            ] as $copy) {
                $this->assertStringNotContainsString($copy, $body,
                    "the vendor {$page} screen has one store, so the wording must not name it");
            }
        }

        $this->assertStringContainsString('name="store_id" id="bundle_store_id"', $screens['create'],
            'the field goes, the value the form posts does not');
    }

    public function test_the_detail_drawer_drops_the_store_row_for_a_vendor(): void
    {
        $bundle = $this->makeBundle($this->store);

        $vendor = $this->actingAsVendor()
            ->get(route('vendor.bundle.view', $bundle->id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->getContent();

        $this->assertStringNotContainsString(translate('messages.Store'), $vendor,
            'the drawer summary names the store only where more than one exists');
        $this->assertStringContainsString(translate('messages.Validity'), $vendor,
            'every other summary row stays');
    }

    /**
     * A vendor switching a bundle off STRANDS it in the carts holding it.
     *
     * Deleting those rows emptied a customer's cart from the vendor's request, and Place Order then
     * reported an empty cart rather than a withdrawn bundle.
     */
    public function test_a_vendor_turning_a_bundle_off_strands_it_in_carts(): void
    {
        $bundle = $this->makeBundle($this->store);

        \Illuminate\Support\Facades\DB::table('carts')->insert([
            'user_id' => 1,
            'item_id' => $bundle->items->first()->item_id,
            'is_guest' => 0,
            'quantity' => 1,
            'price' => 10,
            'bundle_id' => $bundle->id,
            'bundle_group_id' => 'vendor-status-probe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsVendor()
            ->patch(route('vendor.bundle.status', [$bundle->id, 0]))
            ->assertRedirect();

        $this->assertSame(0, (int) $bundle->fresh()->status);
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('carts')
            ->where('bundle_id', $bundle->id)->count(),
            'the row stays so the customer can be told what happened to it');

        $this->assertNotNull(
            app(\App\Services\Promotion\BundleOrderService::class)
                ->blockingReason(['user_id' => 1, 'is_guest' => 0], (int) $bundle->store_id),
            'a switched-off bundle must still be refused at checkout',
        );
    }

    public function test_the_vendor_and_admin_screens_are_the_same_blades(): void
    {
        foreach (['list', 'create', 'edit', 'view'] as $page) {
            $admin = file_get_contents(base_path("resources/views/admin-views/promotions/bundle/{$page}.blade.php"));
            $vendorPage = file_get_contents(base_path("resources/views/vendor-views/promotions/bundle/{$page}.blade.php"));

            foreach (['partials.bundle._list_page', 'partials.bundle._form_page', 'partials.bundle._view_page'] as $partial) {
                $this->assertSame(
                    str_contains($admin, $partial),
                    str_contains($vendorPage, $partial),
                    "{$page} must include the same shared partial on both panels",
                );
            }
        }
    }

    public function test_the_edit_and_detail_screens_render_for_an_own_bundle(): void
    {
        $bundle = $this->makeBundle($this->store);

        $edit = $this->actingAsVendor()->get(route('vendor.bundle.edit', $bundle->id))->assertOk()->getContent();

        $this->assertStringContainsString('const seedItems = ', $edit);
        $this->assertStringContainsString('function onItemConfigured', $edit, 'the shared picker must reach the vendor panel too');
        $this->assertStringContainsString('$(document).on(\'click\', \'.bundle-status-toggle\'', $this->listHtml(),
            'the status toggle handler must be pushed into a stack the vendor layout renders');

        $this->actingAsVendor()->get(route('vendor.bundle.view', $bundle->id))->assertOk()
            ->assertSee(translate('messages.Bundle Activity Status'), false);

        $drawer = $this->actingAsVendor()
            ->get(route('vendor.bundle.view', $bundle->id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<html', $drawer);
        $this->assertStringContainsString(route('vendor.bundle.edit', $bundle->id), $drawer,
            'the drawer must link back into the vendor panel, not the admin one');
    }

    private function listHtml(): string
    {
        return $this->actingAsVendor()->get(route('vendor.bundle.list'))->assertOk()->getContent();
    }

    public function test_a_vendor_creates_a_bundle_for_its_own_store(): void
    {
        $items = $this->plainItems(2);

        $this->actingAsVendor()->post(route('vendor.bundle.store'), [
            'lang' => ['default'],
            'name' => ['Vendor Combo'],
            'store_id' => $this->store->id,
            'start_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'end_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'discount_percentage' => 5,
            'image' => UploadedFile::fake()->image('combo.png'),
            'items' => [['item_id' => $items[0]->id], ['item_id' => $items[1]->id]],
        ])->assertOk()->assertJsonPath('redirect', route('vendor.bundle.list'));

        $bundle = Bundle::where('name', 'Vendor Combo')->firstOrFail();

        $this->assertSame('vendor', $bundle->created_by);
        $this->assertSame($this->store->id, $bundle->store_id);
    }

    public function test_a_vendor_cannot_build_a_bundle_for_another_store(): void
    {
        if (! $this->otherStore) {
            $this->markTestSkipped('dataset has only one store in this module');
        }

        $items = $this->plainItems(2);

        $this->actingAsVendor()->post(route('vendor.bundle.store'), [
            'lang' => ['default'],
            'name' => ['Not Mine'],
            'store_id' => $this->otherStore->id,
            'start_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'end_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'discount_percentage' => 0,
            'image' => UploadedFile::fake()->image('combo.png'),
            'items' => [['item_id' => $items[0]->id], ['item_id' => $items[1]->id]],
        ])->assertForbidden();

        $this->assertDatabaseMissing('bundles', ['name' => 'Not Mine']);
    }

    public function test_another_stores_bundle_is_out_of_reach(): void
    {
        if (! $this->otherStore) {
            $this->markTestSkipped('dataset has only one store in this module');
        }

        $foreign = $this->makeBundle($this->otherStore);

        $this->actingAsVendor()->get(route('vendor.bundle.edit', $foreign->id))->assertNotFound();
        $this->actingAsVendor()->get(route('vendor.bundle.view', $foreign->id))->assertNotFound();
        $this->actingAsVendor()->delete(route('vendor.bundle.delete', $foreign->id))->assertNotFound();

        $this->assertDatabaseHas('bundles', ['id' => $foreign->id, 'deleted_at' => null]);
    }

    public function test_the_list_shows_only_the_vendors_own_bundles(): void
    {
        $mine = $this->makeBundle($this->store);

        $html = $this->actingAsVendor()->get(route('vendor.bundle.list'))->assertOk()->getContent();
        $this->assertStringContainsString($mine->name, $html);

        if ($this->otherStore) {
            $foreign = $this->makeBundle($this->otherStore, name: 'Someone Elses Bundle');
            $html = $this->actingAsVendor()->get(route('vendor.bundle.list'))->assertOk()->getContent();

            $this->assertStringNotContainsString($foreign->name, $html);
        }
    }

    public function test_the_items_endpoint_ignores_a_store_id_in_the_payload(): void
    {
        if (! $this->otherStore) {
            $this->markTestSkipped('dataset has only one store in this module');
        }

        $payload = $this->actingAsVendor()
            ->get(route('vendor.bundle.items', ['store_id' => $this->otherStore->id]))
            ->assertOk()
            ->json();

        $foreignIds = Item::withoutGlobalScopes()->where('store_id', $this->otherStore->id)->pluck('id')->all();

        $this->assertEmpty(
            array_intersect(array_column($payload, 'id'), $foreignIds),
            "the vendor's own store is used, never the one in the request",
        );
    }

    public function test_the_panel_is_closed_while_the_module_is_not_enabled(): void
    {
        $this->storeSettings(status: false);

        $this->actingAsVendor()->get(route('vendor.bundle.list'))->assertNotFound();
        $this->actingAsVendor()->get(route('vendor.bundle.create'))->assertNotFound();
    }

    private function makeBundle(Store $store, string $name = 'Vendor Fixture Bundle'): Bundle
    {
        $items = Item::withoutGlobalScopes()->where('store_id', $store->id)->take(2)->get();

        if ($items->count() < 2) {
            $this->markTestSkipped('the fixture store has too few items');
        }

        $bundle = Bundle::create([
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'name' => $name,
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
            'discount_percentage' => 5,
        ]);

        foreach ($items as $item) {
            BundleItem::create([
                'bundle_id' => $bundle->id,
                'item_id' => $item->id,
                'item_name' => $item->getRawOriginal('name'),
                'item_image' => $item->image,
                'unit_price' => $item->price,
            ]);
        }

        return $bundle->fresh('items');
    }

    private function plainItems(int $count)
    {
        $items = Item::withoutGlobalScopes()->where('store_id', $this->store->id)->take($count)->get();

        if ($items->count() < $count) {
            $this->markTestSkipped('the fixture store has too few items');
        }

        return $items->values();
    }

    private function storeSettings(bool $status): void
    {
        $currentType = $this->store->module?->module_type;
        $map = [];

        foreach (BundleSettings::moduleTypes() as $type) {
            $map[$type] = ($status && $type === $currentType) ? 1 : 0;
        }

        Helpers::businessUpdateOrInsert(['key' => BundleSettings::STATUS_KEY], ['value' => $status ? 1 : 0]);
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::MODULES_KEY], ['value' => json_encode($map)]);
        Helpers::clearBusinessSettingsCache();
    }

    private function actingAsVendor(): self
    {
        return $this->actingAs($this->vendor, 'vendor')
            ->withSession(['login_remember_token' => $this->vendor->login_remember_token]);
    }
}
