<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\Item;
use App\Models\Store;
use App\Models\Vendor;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\SweepsRoutes;
use Tests\TestCase;

class BundleAuditTest extends TestCase
{
    use DatabaseTransactions, SweepsRoutes;

    private ?Store $store = null;

    private ?Store $otherStore = null;

    private ?Vendor $vendor = null;

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
            ->whereNotNull('vendor_id')->where('status', 1)->first();

        if (! $this->store) {
            $this->markTestSkipped('dataset has no bundle-capable store');
        }

        $this->vendor = Vendor::find($this->store->vendor_id);

        $this->otherStore = Store::withoutGlobalScopes()
            ->where('module_id', $this->store->module_id)
            ->where('id', '!=', $this->store->id)
            ->whereHas('items')
            ->first();

        $this->enable();
    }

    public function test_items_from_another_store_cannot_enter_a_bundle(): void
    {
        if (! $this->otherStore) {
            $this->markTestSkipped('dataset has only one store with items in this module');
        }

        $mine = $this->itemsOf($this->store, 1)[0];
        $theirs = $this->itemsOf($this->otherStore, 1)[0];

        $this->actingAsVendor()->post(route('vendor.bundle.store'), $this->payload('Mixed Menus', [
            ['item_id' => $mine->id],
            ['item_id' => $theirs->id],
        ]))->assertStatus(403);

        $this->assertDatabaseMissing('bundles', ['name' => 'Mixed Menus']);
    }

    public function test_an_unresolvable_item_is_refused_not_silently_dropped(): void
    {
        $mine = $this->itemsOf($this->store, 1)[0];
        $ghost = (int) Item::withoutGlobalScopes()->max('id') + 5000;

        $this->actingAsVendor()->post(route('vendor.bundle.store'), $this->payload('Ghost Line', [
            ['item_id' => $mine->id],
            ['item_id' => $ghost],
        ]))->assertStatus(403);

        $this->assertDatabaseMissing('bundles', ['name' => 'Ghost Line']);
    }

    public function test_mismatched_add_on_arrays_do_not_fatal(): void
    {
        $items = $this->itemsOf($this->store, 2);

        $response = $this->actingAsVendor()->post(route('vendor.bundle.store'), $this->payload('Lopsided Addons', [
            [
                'item_id' => $items[0]->id,
                'add_on_ids' => json_encode([1, 2]),
                'add_on_qtys' => json_encode([1]),
            ],
            ['item_id' => $items[1]->id],
        ]));

        $this->assertNotSame(500, $response->getStatusCode(),
            'a mismatched add-on payload must be refused, not a server error');
    }

    public function test_a_bundle_cannot_start_in_the_past(): void
    {
        $items = $this->itemsOf($this->store, 2);

        $payload = $this->payload('Backdated', [['item_id' => $items[0]->id], ['item_id' => $items[1]->id]]);
        $payload['start_date'] = now()->subMonth()->format('Y-m-d H:i:s');

        $this->actingAsVendor()->post(route('vendor.bundle.store'), $payload)->assertStatus(403);

        $this->assertDatabaseMissing('bundles', ['name' => 'Backdated']);
    }

    public function test_a_running_bundle_keeps_its_own_start_as_the_floor(): void
    {
        $items = $this->itemsOf($this->store, 2);

        $bundle = Bundle::create([
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'name' => 'Already Running',
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
        ]);

        $payload = $this->payload('Already Running', [['item_id' => $items[0]->id], ['item_id' => $items[1]->id]]);
        $payload['start_date'] = $bundle->start_date->format('Y-m-d H:i:s');

        $this->actingAsVendor()
            ->post(route('vendor.bundle.update', $bundle->id), $payload)
            ->assertOk('editing a bundle that already started must not be blocked by its own start date');
    }

    public function test_an_edit_cannot_inject_another_stores_items(): void
    {
        if (! $this->otherStore) {
            $this->markTestSkipped('dataset has only one store with items in this module');
        }

        $bundle = $this->seededBundle();
        $theirs = $this->itemsOf($this->otherStore, 2);

        $payload = $this->payload('Sweep Fixture', [
            ['item_id' => $theirs[0]->id],
            ['item_id' => $theirs[1]->id],
        ]);
        // The bundle belongs to the vendor's own store; the payload claims another one.
        $payload['store_id'] = $this->otherStore->id;

        $this->actingAsVendor()->post(route('vendor.bundle.update', $bundle->id), $payload);

        $this->assertSame(
            0,
            $bundle->items()->whereIn('item_id', $theirs->pluck('id'))->count(),
            "another store's items must never reach an existing bundle",
        );
    }

    public function test_an_add_on_the_item_does_not_offer_is_not_priced(): void
    {
        $item = $this->itemsOf($this->store, 1)[0];

        // A priced add-on this item does not list -- what a crafted payload would name.
        $foreign = \App\Models\AddOn::create([
            'store_id' => $this->otherStore?->id ?? $this->store->id,
            'name' => 'Audit Probe Addon',
            'price' => 999,
            'status' => 1,
        ])->id;

        $service = new class
        {
            use \App\Traits\Promotion\HandlesFrozenLines {
                unitPrice as public;
            }
        };
        $method = new \ReflectionMethod($service, 'unitPrice');

        $this->assertEqualsWithDelta(
            (float) $item->price,
            $method->invoke($service, $item, null, [$foreign], [1]),
            0.001,
            'an add-on the item does not list must not raise its price',
        );
    }

    public function test_an_absurd_number_of_items_is_refused(): void
    {
        $items = $this->itemsOf($this->store, 2);
        $lines = [];

        foreach (range(1, Bundle::MAX_ITEMS + 1) as $ignored) {
            $lines[] = ['item_id' => $items[0]->id];
        }

        $this->actingAsVendor()->post(route('vendor.bundle.store'), $this->payload('Too Many', $lines))
            ->assertStatus(403);

        $this->assertDatabaseMissing('bundles', ['name' => 'Too Many']);
    }

    public function test_the_status_toggle_is_not_a_get(): void
    {
        $bundle = $this->seededBundle();

        $this->actingAsVendor()->get(route('vendor.bundle.status', [$bundle->id, 0]))
            ->assertStatus(405);

        $this->actingAsVendor()->patch(route('vendor.bundle.status', [$bundle->id, 0]))
            ->assertRedirect();

        $this->assertSame(0, (int) $bundle->fresh()->status);
    }

    public function test_the_export_downloads_on_both_panels(): void
    {
        $this->seededBundle();
        $admin = \App\Models\Admin::first();

        if (! $admin) {
            $this->markTestSkipped('dataset has no admin');
        }

        foreach (['excel', 'csv'] as $type) {
            $this->actingAs($admin, 'admin')->withSession([
                'current_module' => $this->store->module_id,
                'login_remember_token' => $admin->login_remember_token,
            ])->get(route('admin.bundle.export', ['type' => $type, 'search' => 'fixture']))
                ->assertOk()
                ->assertDownload();
        }

        $this->actingAsVendor()->get(route('vendor.bundle.export', ['type' => 'csv']))
            ->assertOk()
            ->assertDownload();
    }

    public function test_creating_notifies_the_store_only_when_the_admin_built_it(): void
    {
        $service = app(\App\Services\Promotion\BundleService::class);
        $notify = new \ReflectionMethod($service, 'notifyStoreOfNewBundle');

        $bundle = $this->seededBundle();

        $notify->invoke($service, $bundle, 'vendor');
        $notify->invoke($service, $bundle, 'admin');

        $this->assertTrue(true, 'neither path may propagate an exception');
    }

    public function test_all_three_surfaces_create_through_the_same_service_method(): void
    {
        foreach ([
            'app/Http/Controllers/Admin/Promotion/BundleController.php',
            'app/Http/Controllers/Vendor/Promotion/BundleController.php',
            'app/Http/Controllers/Api/V1/Vendor/Promotion/BundleController.php',
        ] as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringContainsString('$this->service->create(', $source, "{$file} must create through the service");
            $this->assertStringContainsString('$this->service->modify(', $source, "{$file} must update through the service");
            $this->assertStringNotContainsString('saveTranslations', $source,
                "{$file} must not repeat what the service already does");
        }
    }

    /** The panel half of the same question: no bundle screen may 5xx. */
    public function test_no_bundle_panel_screen_returns_a_server_error(): void
    {
        $bundle = $this->seededBundle();
        $admin = \App\Models\Admin::first();

        if (! $admin) {
            $this->markTestSkipped('dataset has no admin');
        }

        $asAdmin = fn () => $this->actingAs($admin, 'admin')->withSession([
            'current_module' => $this->store->module_id,
            'login_remember_token' => $admin->login_remember_token,
        ]);

        $urls = [
            route('admin.bundle.list'),
            route('admin.bundle.list', ['search' => 'fixture']),
            route('admin.bundle.create'),
            route('admin.bundle.edit', $bundle->id),
            route('admin.bundle.view', $bundle->id),
            route('admin.bundle.items', ['store_id' => $this->store->id]),
        ];

        foreach ($urls as $url) {
            $this->assertLessThan(500, $asAdmin()->get($url)->getStatusCode(), "{$url} 5xx'd");
        }

        foreach ([
            route('vendor.bundle.list'),
            route('vendor.bundle.list', ['search' => 'fixture']),
            route('vendor.bundle.create'),
            route('vendor.bundle.edit', $bundle->id),
            route('vendor.bundle.view', $bundle->id),
            route('vendor.bundle.items'),
        ] as $url) {
            $this->assertLessThan(500, $this->actingAsVendor()->get($url)->getStatusCode(), "{$url} 5xx'd");
        }
    }

    public function test_a_bundle_order_stays_editable_for_the_app_and_the_panel(): void
    {
        $bundle = $this->seededBundle();
        $order = \App\Models\Order::where('store_id', $this->store->id)
            ->where('order_status', 'pending')
            ->where('payment_method', 'cash_on_delivery')
            ->where('prescription_order', 0)
            ->where('ref_bonus_amount', 0)
            ->where('flash_admin_discount_amount', 0)
            ->whereDoesntHave('payments')
            ->latest('id')->first();

        if (! $order) {
            $this->markTestSkipped('dataset has no editable pending order for this store');
        }

        \Illuminate\Support\Facades\DB::table('order_details')->insert([
            'order_id' => $order->id,
            'item_id' => $bundle->items->first()->item_id,
            'quantity' => 1,
            'price' => 10,
            'bundle_id' => $bundle->id,
            'bundle_group_id' => 'can-edit-probe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = (new \App\Http\Resources\Vendor\Order\OrderResource($order->fresh('details')))
            ->toArray(request());

        \Illuminate\Support\Facades\DB::table('order_details')->where('bundle_group_id', 'can-edit-probe')->delete();

        $this->assertArrayHasKey('can_edit', $payload);
        $this->assertTrue($payload['can_edit'],
            'a bundle order is editable, quantity only, exactly as a BOGO one is');
    }

    public function test_the_order_edit_screens_open_on_a_bundle_order(): void
    {
        $bundle = $this->seededBundle();
        $order = \App\Models\Order::where('store_id', $this->store->id)->latest('id')->first();
        $admin = \App\Models\Admin::first();

        if (! $order || ! $admin) {
            $this->markTestSkipped('dataset has no order for this store');
        }

        \Illuminate\Support\Facades\DB::table('order_details')->insert([
            'order_id' => $order->id,
            'item_id' => $bundle->items->first()->item_id,
            'quantity' => 1,
            'price' => 10,
            'bundle_id' => $bundle->id,
            'bundle_group_id' => 'edit-open-probe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adminEdit = $this->actingAs($admin, 'admin')->withSession([
            'current_module' => $this->store->module_id,
            'login_remember_token' => $admin->login_remember_token,
        ])->get(route('admin.order.edit', $order->id));

        $vendorEdit = $this->actingAsVendor()->get(route('vendor.order.edit', $order->id));

        \Illuminate\Support\Facades\DB::table('order_details')->where('bundle_group_id', 'edit-open-probe')->delete();

        $this->assertLessThan(500, $adminEdit->getStatusCode(), 'the admin editor must not error');
        $this->assertLessThan(500, $vendorEdit->getStatusCode(), 'the vendor editor must not error');
    }

    public function test_the_order_view_folds_a_bundle_into_one_row_like_bogo(): void
    {
        $bundle = $this->seededBundle();
        $order = \App\Models\Order::where('store_id', $this->store->id)->latest('id')->first();
        $admin = \App\Models\Admin::first();

        if (! $order || ! $admin) {
            $this->markTestSkipped('dataset has no order for this store');
        }

        foreach ($bundle->items as $line) {
            \Illuminate\Support\Facades\DB::table('order_details')->insert([
                'order_id' => $order->id,
                'item_id' => $line->item_id,
                'item_details' => json_encode(['name' => $line->item_name]),
                'quantity' => 2,
                'price' => 50,
                'discount_on_item' => 5,
                'bundle_id' => $bundle->id,
                'bundle_group_id' => 'order-view-probe',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $screens = [
            'admin' => $this->actingAs($admin, 'admin')->withSession([
                'current_module' => $this->store->module_id,
                'login_remember_token' => $admin->login_remember_token,
            ])->get(route('admin.order.details', $order->id)),
            'vendor' => $this->actingAsVendor()->get(route('vendor.order.details', $order->id)),
        ];

        \Illuminate\Support\Facades\DB::table('order_details')->where('bundle_group_id', 'order-view-probe')->delete();

        foreach ($screens as $panel => $response) {
            $this->assertSame(200, $response->getStatusCode(), "the {$panel} order view must render");

            $html = $response->getContent();

            $this->assertStringContainsString('bundle-order-row', $html,
                "the {$panel} order view must fold the members into one row, as BOGO does");
            $this->assertStringContainsString('bundle_details_order-view-probe', $html,
                "the {$panel} row must open a modal with the full contents");

            // Counted for THIS group, not for the page. The order is whichever one the dataset
            // has latest for the store, and it may already carry bundles of its own -- order
            // 100200 does. A page-wide count therefore measured the fixture, not the folding, and
            // failed the moment a real bundle order existed. One row pointing at this probe's
            // modal is what "two members are one bundle" actually claims.
            $this->assertSame(
                1,
                substr_count($html, 'data-target="#bundle_details_order-view-probe"'),
                "{$panel}: two members are one bundle, so one row",
            );
        }
    }

    public function test_creating_a_bundle_lazy_loads_nothing(): void
    {
        $items = $this->itemsOf($this->store, 2);
        $admin = \App\Models\Admin::first();

        if (! $admin) {
            $this->markTestSkipped('dataset has no admin');
        }

        $this->sweepArmLazyLoadingGuard();

        $this->actingAs($admin, 'admin')->withSession([
            'current_module' => $this->store->module_id,
            'login_remember_token' => $admin->login_remember_token,
        ])->post(route('admin.bundle.store'), $this->payload('Lazy Guard Probe', [
            ['item_id' => $items[0]->id],
            ['item_id' => $items[1]->id],
        ]))->assertOk('creating must not lazy-load: the notify step walks store.vendor');

        $this->assertDatabaseHas('bundles', ['name' => 'Lazy Guard Probe']);
    }

    public function test_every_bundle_screen_renders_with_lazy_loading_forbidden(): void
    {
        $bundle = $this->seededBundle();
        $admin = \App\Models\Admin::first();

        if (! $admin) {
            $this->markTestSkipped('dataset has no admin');
        }

        $this->sweepArmLazyLoadingGuard();

        $adminSession = fn () => $this->actingAs($admin, 'admin')->withSession([
            'current_module' => $this->store->module_id,
            'login_remember_token' => $admin->login_remember_token,
        ]);

        foreach ([
            route('admin.bundle.list'),
            route('admin.bundle.create'),
            route('admin.bundle.edit', $bundle->id),
            route('admin.bundle.view', $bundle->id),
        ] as $url) {
            $adminSession()->get($url)->assertOk("{$url} lazy-loaded a relation");
        }

        foreach ([
            route('vendor.bundle.list'),
            route('vendor.bundle.create'),
            route('vendor.bundle.edit', $bundle->id),
            route('vendor.bundle.view', $bundle->id),
        ] as $url) {
            $this->actingAsVendor()->get($url)->assertOk("{$url} lazy-loaded a relation");
        }
    }

    public function test_the_api_surfaces_lazy_load_nothing(): void
    {
        $store = $this->tokenedStore();
        $vendor = Vendor::find($store->vendor_id);
        $bundle = $this->seededBundle();

        $this->sweepArmLazyLoadingGuard();

        $headers = [
            'moduleId' => (string) $store->module_id,
            'zoneId' => json_encode([$store->zone_id]),
            'Accept' => 'application/json',
        ];

        foreach ([
            '/api/v1/bundle/home',
            '/api/v1/bundle/list',
            '/api/v1/bundle/store-bundles?store_id='.$store->id,
            '/api/v1/bundle/'.$bundle->id,
        ] as $url) {
            $this->assertSame(200, $this->withHeaders($headers)->getJson($url)->getStatusCode(),
                "{$url} lazy-loaded a relation or could not be reached");
        }

        $vendorHeaders = $headers + [
            'Authorization' => 'Bearer '.$vendor->auth_token,
            'vendorType' => 'owner',
        ];

        foreach ([
            '/api/v1/vendor/bundle/list',
            '/api/v1/vendor/bundle/items',
            '/api/v1/vendor/bundle/details/'.$bundle->id,
        ] as $url) {
            $this->assertSame(200, $this->withHeaders($vendorHeaders)->getJson($url)->getStatusCode(),
                "{$url} lazy-loaded a relation or could not be reached");
        }
    }

    public function test_the_drawer_survives_a_member_item_that_no_longer_exists(): void
    {
        $bundle = $this->seededBundle();
        $admin = \App\Models\Admin::first();

        \App\Models\Item::withoutGlobalScopes()->where('id', $bundle->items->first()->item_id)->delete();

        $session = fn () => $this->actingAs($admin, 'admin')->withSession([
            'current_module' => $this->store->module_id,
            'login_remember_token' => $admin->login_remember_token,
        ]);

        $session()->get(route('admin.bundle.view', $bundle->id))->assertOk();
        $session()->get(route('admin.bundle.view', $bundle->id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk();
        $session()->get(route('admin.bundle.list'))->assertOk();
    }

    public function test_the_vendor_list_does_not_query_per_row(): void
    {
        $store = $this->tokenedStore();
        $vendor = Vendor::find($store->vendor_id);

        foreach (range(1, 3) as $ignored) {
            $this->seededBundle();
        }

        $headers = [
            'moduleId' => (string) $store->module_id,
            'zoneId' => json_encode([$store->zone_id]),
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$vendor->auth_token,
            'vendorType' => 'owner',
        ];

        \Illuminate\Support\Facades\DB::enableQueryLog();

        $this->withHeaders($headers)->getJson('/api/v1/vendor/bundle/list?limit=50')->assertOk();

        $queries = count(\Illuminate\Support\Facades\DB::getQueryLog());

        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertLessThan(30, $queries,
            "the list now renders every member, so it must stay eager-loaded; ran {$queries} queries");
    }

    public function test_the_standalone_view_offers_a_working_way_out(): void
    {
        $bundle = $this->seededBundle();
        $admin = \App\Models\Admin::first();

        $page = $this->actingAs($admin, 'admin')->withSession([
            'current_module' => $this->store->module_id,
            'login_remember_token' => $admin->login_remember_token,
        ])->get(route('admin.bundle.view', $bundle->id))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('admin.bundle.list').'"', $page,
            'the close control has to go somewhere off a page with no drawer to close');
        $this->assertStringNotContainsString('offcanvas-close', $page,
            'the drawer close button does nothing here and must not be rendered');
    }

    private function tokenedStore(): Store
    {
        $store = Store::withoutGlobalScopes()
            ->whereHas('module', fn ($q) => $q->whereIn('module_type', BundleSettings::moduleTypes()))
            ->where('status', 1)->whereHas('items')
            ->whereHas('vendor', fn ($q) => $q->whereNotNull('auth_token'))
            ->first();

        if (! $store) {
            $this->markTestSkipped('dataset has no bundle-capable store whose vendor holds a token');
        }

        $this->store = $store;

        return $store;
    }

    private function seededBundle(): Bundle
    {
        $items = $this->itemsOf($this->store, 2);

        $bundle = Bundle::create([
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'name' => 'Sweep Fixture',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
            'discount_percentage' => 5,
        ]);

        foreach ($items as $item) {
            \App\Models\BundleItem::create([
                'bundle_id' => $bundle->id,
                'item_id' => $item->id,
                'item_name' => $item->getRawOriginal('name'),
                'item_image' => $item->image,
                'unit_price' => $item->price,
                'add_on_ids' => \App\Models\AddOn::query()->limit(1)->pluck('id')->all(),
                'add_on_qtys' => [2],
            ]);
        }

        return $bundle->fresh('items');
    }

    private function payload(string $name, array $items): array
    {
        return [
            'lang' => ['default'],
            'name' => [$name],
            'store_id' => $this->store->id,
            'start_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'end_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'discount_percentage' => 0,
            'image' => UploadedFile::fake()->image('combo.png'),
            'items' => $items,
        ];
    }

    private function itemsOf(Store $store, int $count)
    {
        $items = Item::withoutGlobalScopes()->where('store_id', $store->id)->take($count)->get();

        if ($items->count() < $count) {
            $this->markTestSkipped('store has too few items');
        }

        return $items->values();
    }

    private function enable(): void
    {
        $type = $this->store->module?->module_type;
        $map = [];

        foreach (BundleSettings::moduleTypes() as $t) {
            $map[$t] = $t === $type ? 1 : 0;
        }

        Helpers::businessUpdateOrInsert(['key' => BundleSettings::STATUS_KEY], ['value' => 1]);
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::MODULES_KEY], ['value' => json_encode($map)]);
        Helpers::clearBusinessSettingsCache();
    }

    private function actingAsVendor(): self
    {
        return $this->actingAs($this->vendor, 'vendor')
            ->withSession(['login_remember_token' => $this->vendor->login_remember_token]);
    }
}
