<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\Store;
use App\Models\Vendor;
use App\Services\Promotion\BundleService;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Modules\Service\Entities\Service;
use Tests\TestCase;

class BundleServiceVendorTest extends TestCase
{
    use DatabaseTransactions;

    private ?Store $store = null;

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
            ->whereHas('module', fn ($q) => $q->where('module_type', 'service'))
            ->whereHas('services')
            ->whereHas('vendor')
            ->first();

        if (! $this->store) {
            $this->markTestSkipped('dataset has no service provider with services');
        }

        $this->vendor = Vendor::find($this->store->vendor_id);

        $map = [];
        foreach (BundleSettings::moduleTypes() as $type) {
            $map[$type] = $type === 'service' ? 1 : 0;
        }
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::STATUS_KEY], ['value' => 1]);
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::MODULES_KEY], ['value' => json_encode($map)]);
        Helpers::clearBusinessSettingsCache();
    }

    public function test_every_vendor_panel_screen_opens_for_a_provider(): void
    {
        $bundle = $this->serviceBundle();

        foreach ([
            route('vendor.bundle.list'),
            route('vendor.bundle.create'),
            route('vendor.bundle.edit', $bundle->id),
            route('vendor.bundle.view', $bundle->id),
        ] as $url) {
            $response = $this->actingAsVendor()->get($url);

            $this->assertSame(200, $response->getStatusCode(), "{$url} did not open for a provider");
        }
    }

    public function test_the_panel_calls_the_owner_a_provider_and_offers_services(): void
    {
        $create = $this->actingAsVendor()->get(route('vendor.bundle.create'))->assertOk()->getContent();

        $this->assertStringContainsString(translate('messages.Select Service'), $create,
            'a provider picks services, not items');
        $this->assertStringNotContainsString(translate('messages.Select Bundle Item'), $create);

        $items = $this->actingAsVendor()->getJson(route('vendor.bundle.items'))->assertOk()->json();

        $this->assertNotEmpty($items);
        $this->assertTrue($items[0]['is_service'], 'the panel picker answers with services');
        $this->assertArrayHasKey('variants', $items[0]);
    }

    public function test_a_provider_creates_a_service_bundle_from_the_panel(): void
    {
        $services = $this->bookableServices();

        $response = $this->actingAsVendor()->post(route('vendor.bundle.store'), [
            'lang' => ['default'],
            'name' => ['Panel Service Combo'],
            'store_id' => $this->store->id,
            'start_date' => now()->addDay()->format('Y-m-d H:i'),
            'end_date' => now()->addMonth()->format('Y-m-d H:i'),
            'discount_percentage' => 10,
            'items' => [
                ['service_id' => $services[0]->id],
                ['service_id' => $services[1]->id],
            ],
            'image' => UploadedFile::fake()->image('combo.png'),
        ]);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $bundle = Bundle::where('store_id', $this->store->id)
            ->where('name', 'Panel Service Combo')->latest('id')->first();

        $this->assertNotNull($bundle, 'the bundle must be saved');
        $this->assertCount(2, $bundle->items);

        foreach ($bundle->items as $line) {
            $this->assertTrue($line->isService());
            $this->assertNull($line->item_id);
        }

        $this->assertEqualsWithDelta(
            $bundle->items->sum('unit_price'),
            (float) $bundle->base_price,
            0.01,
        );
    }

    public function test_a_provider_cannot_bundle_another_providers_service(): void
    {
        $other = Service::withoutGlobalScopes()
            ->where('store_id', '!=', $this->store->id)->first();

        if (! $other) {
            $this->markTestSkipped('dataset has only one provider with services');
        }

        $mine = $this->bookableServices()[0];

        $response = $this->actingAsVendor()->post(route('vendor.bundle.store'), [
            'lang' => ['default'],
            'name' => ['Cross Provider'],
            'store_id' => $this->store->id,
            'start_date' => now()->addDay()->format('Y-m-d H:i'),
            'end_date' => now()->addMonth()->format('Y-m-d H:i'),
            'discount_percentage' => 10,
            'items' => [['service_id' => $mine->id], ['service_id' => $other->id]],
            'image' => UploadedFile::fake()->image('x.png'),
        ]);

        $this->assertSame(403, $response->getStatusCode(),
            'a service another provider offers must not enter this bundle');
    }

    public function test_status_and_delete_work_from_the_provider_panel(): void
    {
        $bundle = $this->serviceBundle();

        $this->actingAsVendor()
            ->patch(route('vendor.bundle.status', [$bundle->id, 0]))
            ->assertRedirect();

        $this->assertSame(0, (int) $bundle->fresh()->status);

        $this->actingAsVendor()
            ->delete(route('vendor.bundle.delete', $bundle->id))
            ->assertRedirect();

        $this->assertNull(Bundle::find($bundle->id), 'a provider can remove its own bundle');
    }

    public function test_the_vendor_api_mirrors_the_panel_for_a_provider(): void
    {
        if (! $this->vendor?->auth_token) {
            $this->markTestSkipped('the fixture provider has no tokened vendor');
        }

        $bundle = $this->serviceBundle();

        $headers = [
            'moduleId' => (string) $this->store->module_id,
            'zoneId' => json_encode([$this->store->zone_id]),
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->vendor->auth_token,
            'vendorType' => 'owner',
        ];

        $detail = $this->withHeaders($headers)
            ->getJson('/api/v1/vendor/bundle/details/'.$bundle->id)
            ->assertOk()->json('content');

        $this->assertSame($bundle->id, $detail['id']);
        $this->assertCount($bundle->items->count(), $detail['items']);

        foreach ($detail['items'] as $line) {
            $this->assertTrue($line['is_service']);
            $this->assertNull($line['item_id']);
        }

        $this->withHeaders($headers)
            ->postJson('/api/v1/vendor/bundle/status/'.$bundle->id, ['status' => 0])
            ->assertOk();

        $this->assertSame(0, (int) $bundle->fresh()->status);

        $this->withHeaders($headers)
            ->deleteJson('/api/v1/vendor/bundle/delete/'.$bundle->id)
            ->assertOk();

        $this->assertNull(Bundle::find($bundle->id));
    }

    public function test_the_vendor_api_creates_a_service_bundle(): void
    {
        if (! $this->vendor?->auth_token) {
            $this->markTestSkipped('the fixture provider has no tokened vendor');
        }

        $services = $this->bookableServices();

        $headers = [
            'moduleId' => (string) $this->store->module_id,
            'zoneId' => json_encode([$this->store->zone_id]),
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->vendor->auth_token,
            'vendorType' => 'owner',
        ];

        $created = $this->withHeaders($headers)->post('/api/v1/vendor/bundle/store', [
            'lang' => ['default'],
            'name' => ['Api Service Combo'],
            'store_id' => $this->store->id,
            'start_date' => now()->addDay()->format('Y-m-d H:i'),
            'end_date' => now()->addMonth()->format('Y-m-d H:i'),
            'discount_percentage' => 20,
            'items' => [
                ['service_id' => $services[0]->id],
                ['service_id' => $services[1]->id],
            ],
            'image' => UploadedFile::fake()->image('api.png'),
        ]);

        $this->assertSame(201, $created->getStatusCode(), $created->getContent());

        $payload = $created->json('content');

        $this->assertCount(2, $payload['items']);

        foreach ($payload['items'] as $line) {
            $this->assertTrue($line['is_service']);
            $this->assertNotNull($line['service_id']);
        }

        $this->assertEqualsWithDelta(
            collect($payload['items'])->sum('unit_price'),
            (float) $payload['base_price'],
            0.01,
            'the API prices a service bundle from its frozen lines',
        );
    }

    public function test_the_api_refuses_a_line_with_no_service(): void
    {
        if (! $this->vendor?->auth_token) {
            $this->markTestSkipped('the fixture provider has no tokened vendor');
        }

        $services = $this->bookableServices();

        $response = $this->withHeaders([
            'moduleId' => (string) $this->store->module_id,
            'zoneId' => json_encode([$this->store->zone_id]),
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->vendor->auth_token,
            'vendorType' => 'owner',
        ])->post('/api/v1/vendor/bundle/store', [
            'lang' => ['default'],
            'name' => ['Missing Target'],
            'store_id' => $this->store->id,
            'start_date' => now()->addDay()->format('Y-m-d H:i'),
            'end_date' => now()->addMonth()->format('Y-m-d H:i'),
            'discount_percentage' => 10,
            'items' => [['item_id' => 1], ['service_id' => $services[0]->id]],
            'image' => UploadedFile::fake()->image('x.png'),
        ]);

        $this->assertSame(403, $response->getStatusCode(),
            'a service module needs service_id on every line');
        $this->assertSame('items.0.service_id', $response->json('errors.0.code'));
    }

    public function test_the_provider_export_downloads_and_says_provider(): void
    {
        $this->serviceBundle();

        $response = $this->actingAsVendor()->get(route('vendor.bundle.export', ['type' => 'excel']));

        $this->assertSame(200, $response->getStatusCode(), 'a provider must be able to export its bundles');
        $this->assertNotEmpty($response->streamedContent() ?: $response->getContent());

        $label = app(BundleService::class)->ownerLabel((int) $this->store->module_id);

        $this->assertSame(translate('messages.Provider'), $label,
            'the export header follows the module, like every other surface');
    }

    private function bookableServices()
    {
        $services = Service::active(null, (int) $this->store->module_id)
            ->where('store_id', $this->store->id)
            ->take(2)->get();

        if ($services->count() < 2) {
            $this->markTestSkipped('the fixture provider has too few bookable services');
        }

        return $services;
    }

    private function serviceBundle(): Bundle
    {
        $services = $this->bookableServices();

        $bundle = Bundle::create([
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'name' => 'Provider Panel Bundle',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
            'discount_percentage' => 10,
        ]);

        app(BundleService::class)->syncItems($bundle, [
            ['service_id' => $services[0]->id],
            ['service_id' => $services[1]->id],
        ]);

        return $bundle->fresh('items');
    }

    private function actingAsVendor(): self
    {
        return $this->actingAs($this->vendor, 'vendor')
            ->withSession(['login_remember_token' => $this->vendor->login_remember_token]);
    }
}
