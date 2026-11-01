<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Service\Entities\Service;
use Tests\TestCase;

class BundleServiceModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        \App\CentralLogics\Helpers::clearBusinessSettingsCache();
        parent::tearDown();
    }

    public function test_a_service_module_may_carry_a_bundle(): void
    {
        $this->assertContains('service', BundleSettings::moduleTypes());

        foreach (['parcel', 'rental', 'ride-share'] as $type) {
            $this->assertNotContains($type, BundleSettings::moduleTypes());
        }
    }

    public function test_a_bundle_line_may_name_a_service_instead_of_an_item(): void
    {
        $this->assertTrue(Schema::hasColumn('bundle_items', 'service_id'));

        $service = Service::withoutGlobalScopes()->first();

        if (! $service) {
            $this->markTestSkipped('dataset has no service');
        }

        $bundle = $this->bundle();

        $line = BundleItem::create([
            'bundle_id' => $bundle->id,
            'service_id' => $service->id,
            'item_name' => 'Deep Clean',
            'unit_price' => 40,
        ]);

        $this->assertTrue($line->isService());
        $this->assertSame((int) $service->id, $line->targetId());
        $this->assertNull($line->item_id);
    }

    public function test_a_line_naming_both_targets_is_refused_by_the_model(): void
    {
        $bundle = $this->bundle();

        $this->expectException(InvalidArgumentException::class);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_id' => 1,
            'service_id' => 1,
            'item_name' => 'Both',
            'unit_price' => 10,
        ]);
    }

    public function test_a_line_naming_no_target_is_refused_by_the_model(): void
    {
        $bundle = $this->bundle();

        $this->expectException(InvalidArgumentException::class);

        BundleItem::create([
            'bundle_id' => $bundle->id,
            'item_name' => 'Neither',
            'unit_price' => 10,
        ]);
    }

    public function test_the_database_enforces_one_target_even_without_the_model(): void
    {
        $bundle = $this->bundle();

        $this->expectExceptionMessageMatches('/bundle_items_one_target/');

        DB::table('bundle_items')->insert([
            'bundle_id' => $bundle->id,
            'item_id' => 1,
            'service_id' => 1,
            'item_name' => 'Raw both',
            'unit_price' => 10,
            'quantity' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_service_booking_line_can_carry_a_bundle(): void
    {
        foreach (['bundle_id', 'bundle_group_id'] as $column) {
            $this->assertTrue(Schema::hasColumn('service_booking_details', $column),
                "service_booking_details.{$column} is missing");
        }

        $this->assertTrue(Schema::hasColumn('service_bookings', 'bundle_discount_amount'));
    }

    public function test_the_picker_offers_services_in_a_service_module(): void
    {
        $store = $this->serviceStore();
        $this->enableBundlesForServices();

        $options = app(\App\Services\Promotion\BundleService::class)
            ->pickerOptions((int) $store->id, null, (int) $store->module_id);

        $this->assertNotEmpty($options, 'a service store must have something to bundle');

        $row = $options[0];

        $this->assertTrue($row['is_service'], 'a service module offers services, never items');

        foreach (['id', 'name', 'price', 'discounted_price', 'variants', 'requires_variant', 'is_available'] as $key) {
            $this->assertArrayHasKey($key, $row, "the service picker row is missing {$key}");
        }

        $this->assertArrayNotHasKey('add_ons', $row, 'a service carries no add-ons');
        $this->assertArrayNotHasKey('stock', $row, 'and no stock');
    }

    public function test_a_service_line_freezes_the_gross_price_not_the_discounted_one(): void
    {
        $service = Service::withoutGlobalScopes()->whereNotNull('variations')->first();

        if (! $service) {
            $this->markTestSkipped('dataset has no service with variants');
        }

        $resolver = new class
        {
            use \App\Traits\Promotion\HandlesFrozenLines {
                serviceUnitPrice as public;
            }
        };

        $variants = json_decode($service->getRawOriginal('variations'), true) ?: [];
        $variant = $variants[0] ?? null;

        $this->assertNotNull($variant, 'fixture needs a variant');

        $this->assertSame(
            (float) $variant['price'],
            $resolver->serviceUnitPrice($service, [['variant_key' => $variant['variant_key']]]),
            'a bundle is already the promotion, so it freezes the gross price of the variant',
        );

        $this->assertSame(
            (float) $service->base_price,
            $resolver->serviceUnitPrice($service, []),
            'with no variant chosen the base price is what is frozen',
        );
    }

    public function test_a_service_bundle_saves_its_lines_against_services(): void
    {
        $store = $this->serviceStore();

        $services = Service::withoutGlobalScopes()->where('store_id', $store->id)->take(2)->get();

        if ($services->count() < 2) {
            $this->markTestSkipped('the fixture service store has too few services');
        }

        $bundle = Bundle::create([
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'name' => 'Service Combo',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
            'discount_percentage' => 10,
        ]);

        app(\App\Services\Promotion\BundleService::class)->syncItems($bundle, [
            ['service_id' => $services[0]->id],
            ['service_id' => $services[1]->id],
        ]);

        $lines = $bundle->fresh('items')->items;

        $this->assertCount(2, $lines);

        foreach ($lines as $line) {
            $this->assertTrue($line->isService());
            $this->assertNull($line->item_id);
            $this->assertGreaterThan(0, (float) $line->unit_price);
        }

        $this->assertEqualsWithDelta(
            $lines->sum('unit_price'),
            (float) $bundle->fresh()->base_price,
            0.01,
            'the base price is the sum of the frozen service prices',
        );
    }

    public function test_a_service_bundle_writes_service_rows_into_the_cart(): void
    {
        $store = $this->serviceStore();
        $this->enableBundlesForServices();

        $bundle = $this->serviceBundle($store);

        $result = app(\App\Services\Promotion\BundleCartService::class)->addBundle([
            'bundle_id' => $bundle->id,
            'quantity' => 2,
            'user_id' => 'service-bundle-guest',
            'is_guest' => 1,
            'module_id' => $store->module_id,
            'zone_ids' => [],
        ]);

        $this->assertSame(200, $result['status_code'], json_encode($result));

        $rows = \App\Models\Cart::where('bundle_group_id', $result['bundle_group_id'])->get();

        $this->assertCount($bundle->items->count(), $rows);

        foreach ($rows as $row) {
            $this->assertSame(Service::class, $row->item_type,
                'a service bundle writes service rows, not item rows');
            $this->assertContains((int) $row->item_id, $bundle->items->pluck('service_id')->all());
            $this->assertSame(2, (int) $row->quantity);
        }

        \App\Models\Cart::where('bundle_group_id', $result['bundle_group_id'])->delete();
    }

    public function test_a_withdrawn_service_makes_its_bundle_unavailable(): void
    {
        $store = $this->serviceStore();
        $this->enableBundlesForServices();

        $bundle = $this->serviceBundle($store);
        $first = $bundle->items->first();
        $service = Service::withoutGlobalScopes()->find($first->service_id);
        $was = $service->status;

        $service->forceFill(['status' => 0])->saveQuietly();

        $result = app(\App\Services\Promotion\BundleCartService::class)->addBundle([
            'bundle_id' => $bundle->id,
            'quantity' => 1,
            'user_id' => 'service-bundle-guest-2',
            'is_guest' => 1,
            'module_id' => $store->module_id,
            'zone_ids' => [],
        ]);

        $service->forceFill(['status' => $was])->saveQuietly();

        $this->assertSame(403, $result['status_code'], 'a dead member must stop the whole bundle');
        $this->assertStringContainsString($service->name, $result['message'],
            'the refusal names the service at fault');
    }

    public function test_a_bundle_line_keeps_its_frozen_price_through_booking_pricing(): void
    {
        $store = $this->serviceStore();
        $this->enableBundlesForServices();

        $bundle = $this->serviceBundle($store);
        $lines = [];

        foreach ($bundle->items as $line) {
            $lines[] = [
                'service_id' => $line->service_id,
                'quantity' => 1,
                'price' => (float) $line->unit_price,
                'variation' => $line->variations ?: null,
                'bundle_id' => $bundle->id,
                'bundle_group_id' => 'booking-probe',
            ];
        }

        $result = app(\Modules\Service\Services\ServiceBookingService::class)
            ->buildBookingDetails($store, $lines, 1);

        $this->assertArrayNotHasKey('code', $result, json_encode($result));

        $details = $result['details_data'];

        $this->assertCount($bundle->items->count(), $details);

        foreach ($details as $index => $row) {
            $this->assertSame('booking-probe', $row['bundle_group_id'], 'the group reaches the booking line');
            $this->assertSame($bundle->id, $row['bundle_id']);
            $this->assertSame('vendor', $row['discount_by'], 'a bundle reduction is the store own');
            $this->assertEqualsWithDelta(
                (float) $bundle->items[$index]->unit_price,
                (float) $row['original_price'],
                0.01,
                'a member is booked at the price the bundle froze, never today service price',
            );
        }

        $reduction = collect($details)->sum(fn ($row) => (float) $row['discount_amount'] * (int) $row['quantity']);

        $this->assertEqualsWithDelta(
            (float) $bundle->base_price - (float) $bundle->discounted_price,
            $reduction,
            0.02,
            'the distributed shares add up to exactly what the bundle takes off',
        );

        $this->assertEqualsWithDelta(
            (float) $bundle->base_price - (float) $bundle->discounted_price,
            (float) $result['bundle_discount_amount'],
            0.02,
            'and the booking records it',
        );
    }

    public function test_a_provider_wide_rate_does_not_take_over_a_bundle_booking(): void
    {
        $source = file_get_contents(base_path('Modules/Service/Traits/BookingCheckoutTrait.php'));

        $this->assertStringContainsString(
            "\$hasBundleLines = collect(\$details_data)->contains(fn (\$row) => ! empty(\$row['bundle_group_id']));",
            $source,
            'a booking holding a bundle must not let the provider rate take over',
        );

        $this->assertStringContainsString('&& ! $hasBundleLines)', $source,
            'the provider series is not even computed while a bundle is present');

        $this->assertStringContainsString('app(BundleOrderService::class)->distributeReduction', $source,
            'the booking path shares the order path arithmetic rather than repeating it');
    }

    public function test_the_bundle_form_offers_services_in_a_service_module(): void
    {
        $store = $this->serviceStore();
        $this->enableBundlesForServices();

        $data = app(\App\Services\Promotion\BundleService::class)->formData(
            bundle: null,
            stores: collect([(object) ['id' => $store->id, 'name' => $store->name]]),
            moduleId: (int) $store->module_id,
            storeId: (int) $store->id,
            routePrefix: 'vendor.bundle',
            action: '#',
            heading: 'h',
            submitLabel: 's',
            successMessage: 'm',
        );

        $this->assertTrue($data['isServiceModule'], 'the form has to know it is building a service bundle');
    }

    public function test_the_picker_script_posts_a_service_target(): void
    {
        $script = file_get_contents(base_path('resources/views/partials/bundle/_picker_scripts.blade.php'));

        $this->assertStringContainsString("fields.push([prefix + '[service_id]', item.service_id]);", $script,
            'a service bundle posts service_id, which is what the request validates');
        $this->assertStringContainsString('function addServiceToBundle', $script);
    }

    public function test_picking_a_service_opens_a_config_popup_like_an_item(): void
    {
        $script = file_get_contents(base_path('resources/views/partials/bundle/_picker_scripts.blade.php'));

        $this->assertStringContainsString('openServiceConfig(food, null, {index: null});', $script,
            'picking a service must open a popup, exactly as picking an item does');
        $this->assertStringContainsString("\$('#serviceConfigModal').modal('show');", $script);
        $this->assertStringContainsString('bundle-service-edit', $script,
            'a chosen service can be reopened to change its variant');
        $this->assertStringNotContainsString('bundle-service-variant', $script,
            'the inline drop-down is replaced by the popup');

        $modal = file_get_contents(base_path('resources/views/partials/bundle/_service_options_modal.blade.php'));

        foreach (['sc_image', 'sc_name', 'sc_price', 'sc_variants', 'sc_total', 'sc_submit'] as $hook) {
            $this->assertStringContainsString($hook, $modal, "the service popup is missing {$hook}");
        }

        $page = file_get_contents(base_path('resources/views/partials/bundle/_form_page.blade.php'));

        $this->assertStringContainsString('partials.bundle._service_options_modal', $page,
            'the form has to render the popup it opens');
    }

    public function test_the_booking_view_folds_a_bundle_into_one_row(): void
    {
        $blade = file_get_contents(base_path(
            'Modules/Service/Resources/views/admin/booking/partials/_repeat-booking-details-left-section.blade.php'
        ));

        $this->assertStringContainsString('->orderGroups($booking->details)', $blade,
            'a booking folds its bundle the way an order does');
        $this->assertStringContainsString("@forelse (\$bundleGroups['plain'] as \$i => \$detail)", $blade,
            'members must not also be listed as loose lines');
        $this->assertStringContainsString("@foreach (\$bundleGroups['bundles'] as \$bundle)", $blade);
    }

    public function test_the_customer_api_serves_a_service_bundle(): void
    {
        $store = $this->serviceStore();
        $this->enableBundlesForServices();

        $bundle = $this->serviceBundle($store);

        $headers = [
            'moduleId' => (string) $store->module_id,
            'zoneId' => json_encode([$store->zone_id]),
            'Accept' => 'application/json',
        ];

        $list = $this->withHeaders($headers)->getJson('/api/v1/bundle/list')->assertOk()->json('content.data');

        $this->assertContains($bundle->id, array_column($list, 'id'),
            'a service bundle must reach the customer list');

        $detail = $this->withHeaders($headers)->getJson('/api/v1/bundle/'.$bundle->id)
            ->assertOk()->json('content');

        $this->assertCount($bundle->items->count(), $detail['items']);

        foreach ($detail['items'] as $line) {
            $this->assertTrue($line['is_service'], 'the payload says which target this line names');
            $this->assertNotNull($line['service_id']);
            $this->assertNull($line['item_id']);
            $this->assertGreaterThan(0, $line['unit_price']);
        }

        $this->assertTrue($detail['is_available'], 'a live service bundle is bookable');
    }

    public function test_the_vendor_api_builds_and_reads_a_service_bundle(): void
    {
        $store = $this->serviceStore();
        $this->enableBundlesForServices();

        $vendor = \App\Models\Vendor::find($store->vendor_id);

        if (! $vendor?->auth_token) {
            $this->markTestSkipped('the fixture service store has no tokened vendor');
        }

        $headers = [
            'moduleId' => (string) $store->module_id,
            'zoneId' => json_encode([$store->zone_id]),
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$vendor->auth_token,
            'vendorType' => 'owner',
        ];

        $picker = $this->withHeaders($headers)->getJson('/api/v1/vendor/bundle/items')
            ->assertOk()->json('content.items');

        $this->assertNotEmpty($picker);
        $this->assertTrue($picker[0]['is_service'], 'the vendor picker offers services in a service module');

        $bundle = $this->serviceBundle($store);

        $row = collect($this->withHeaders($headers)->getJson('/api/v1/vendor/bundle/list')
            ->assertOk()->json('content.data'))->firstWhere('id', $bundle->id);

        $this->assertNotNull($row, 'the vendor list must show the service bundle');

        foreach ($row['items'] as $line) {
            $this->assertTrue($line['is_service']);
            $this->assertNotNull($line['service_id']);
        }
    }

    public function test_every_service_sidebar_variant_links_to_bundles(): void
    {
        $variants = [
            'Modules/Service/Resources/views/admin/partials/_sidebar_v2_service.blade.php',
            'Modules/Service/Resources/views/admin/partials/_sidebar_service.blade.php',
            'Modules/Service/Resources/views/vendor/partials/_sidebar_v2_service.blade.php',
            'Modules/Service/Resources/views/vendor/partials/_sidebar_service.blade.php',
        ];

        foreach ($variants as $variant) {
            $blade = file_get_contents(base_path($variant));
            $name = basename($variant);

            $this->assertStringContainsString('bundle.list', $blade,
                "{$name}: a service panel has no way to reach bundles");
            $this->assertStringContainsString('BundleSettings::allowsModule', $blade,
                "{$name}: the link must ask the same switch the controller asks");
        }
    }

    public function test_the_service_sidebars_are_the_ones_the_layout_includes(): void
    {
        foreach (['admin', 'vendor'] as $panel) {
            $layout = file_get_contents(base_path("resources/views/layouts/{$panel}/app.blade.php"));

            $this->assertMatchesRegularExpression(
                '/service::'.$panel.'\.partials\._sidebar_v2_service/',
                $layout,
                "the {$panel} layout must be the one including the v2 service sidebar we edited",
            );
            $this->assertMatchesRegularExpression(
                '/service::'.$panel.'\.partials\._sidebar_service/',
                $layout,
                "and the v1 one too",
            );
        }
    }

    public function test_a_service_module_calls_the_owner_a_provider(): void
    {
        $store = $this->serviceStore();
        $this->enableBundlesForServices();

        $service = app(\App\Services\Promotion\BundleService::class);

        $this->assertSame(translate('messages.Provider'), $service->ownerLabel((int) $store->module_id));

        $productModule = \App\Models\Module::where('module_type', '!=', 'service')->first();

        if ($productModule) {
            $this->assertSame(translate('messages.Store'), $service->ownerLabel((int) $productModule->id),
                'every other module still says Store');
        }

        $list = $service->panelList(null, (int) $store->module_id, null, 'admin.bundle');

        $this->assertSame(translate('messages.Provider'), $list['ownerLabel'],
            'the list column header follows the module');

        $bundle = $this->serviceBundle($store);
        $detail = $service->detailData($bundle, 'admin.bundle', showStore: true);

        $this->assertArrayHasKey(translate('messages.Provider'), $detail['summaryRows'],
            'and so does the drawer summary row');
        $this->assertArrayNotHasKey(translate('messages.Store'), $detail['summaryRows']);
    }

    private function serviceBundle($store): Bundle
    {
        $services = Service::active(null, (int) $store->module_id)
            ->where('store_id', $store->id)
            ->take(2)->get();

        if ($services->count() < 2) {
            $this->markTestSkipped('the fixture service store has too few bookable services');
        }

        $bundle = Bundle::create([
            'store_id' => $store->id,
            'module_id' => $store->module_id,
            'name' => 'Cart Service Bundle',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
            'discount_percentage' => 10,
        ]);

        app(\App\Services\Promotion\BundleService::class)->syncItems($bundle, [
            ['service_id' => $services[0]->id],
            ['service_id' => $services[1]->id],
        ]);

        return $bundle->fresh('items');
    }

    private function enableBundlesForServices(): void
    {
        $map = [];

        foreach (BundleSettings::moduleTypes() as $type) {
            $map[$type] = $type === 'service' ? 1 : 0;
        }

        \App\CentralLogics\Helpers::businessUpdateOrInsert(['key' => BundleSettings::STATUS_KEY], ['value' => 1]);
        \App\CentralLogics\Helpers::businessUpdateOrInsert(['key' => BundleSettings::MODULES_KEY], ['value' => json_encode($map)]);
        \App\CentralLogics\Helpers::clearBusinessSettingsCache();
    }

    private function serviceStore()
    {
        $store = \App\Models\Store::withoutGlobalScopes()
            ->whereHas('module', fn ($q) => $q->where('module_type', 'service'))
            ->whereHas('services')
            ->first();

        if (! $store) {
            $this->markTestSkipped('dataset has no service store with services');
        }

        return $store;
    }

    private function bundle(): Bundle
    {
        return Bundle::create([
            'store_id' => 1,
            'module_id' => 1,
            'name' => 'Service Bundle Fixture',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
        ]);
    }
}
