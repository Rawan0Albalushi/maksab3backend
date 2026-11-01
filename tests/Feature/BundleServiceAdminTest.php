<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Admin;
use App\Models\Bundle;
use App\Models\Store;
use App\Services\Promotion\BundleService;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Modules\Service\Entities\Service;
use Tests\TestCase;

class BundleServiceAdminTest extends TestCase
{
    use DatabaseTransactions;

    private ?Store $store = null;

    private ?Admin $admin = null;

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
            ->whereHas('services')->first();

        $this->admin = Admin::first();

        if (! $this->store || ! $this->admin) {
            $this->markTestSkipped('dataset has no service provider or admin');
        }

        $map = [];
        foreach (BundleSettings::moduleTypes() as $type) {
            $map[$type] = $type === 'service' ? 1 : 0;
        }
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::STATUS_KEY], ['value' => 1]);
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::MODULES_KEY], ['value' => json_encode($map)]);
        Helpers::clearBusinessSettingsCache();
    }

    public function test_every_admin_screen_opens_under_a_service_module(): void
    {
        $bundle = $this->serviceBundle();

        foreach ([
            route('admin.bundle.list'),
            route('admin.bundle.create'),
            route('admin.bundle.edit', $bundle->id),
            route('admin.bundle.view', $bundle->id),
        ] as $url) {
            $response = $this->actingAsAdmin()->get($url);

            $this->assertSame(200, $response->getStatusCode(), "{$url} did not open under a service module");
        }
    }

    public function test_the_admin_form_says_provider_and_offers_services(): void
    {
        $create = $this->actingAsAdmin()->get(route('admin.bundle.create'))->assertOk()->getContent();

        $this->assertStringContainsString(translate('messages.Provider'), $create,
            'a service module calls the owner a provider');
        $this->assertStringContainsString(translate('messages.Select Service'), $create);

        $items = $this->actingAsAdmin()
            ->getJson(route('admin.bundle.items', ['store_id' => $this->store->id]))
            ->assertOk()->json();

        $this->assertNotEmpty($items);
        $this->assertTrue($items[0]['is_service']);
    }

    public function test_the_admin_list_and_drawer_name_the_provider(): void
    {
        $bundle = $this->serviceBundle();

        $list = $this->actingAsAdmin()->get(route('admin.bundle.list'))->assertOk()->getContent();

        $this->assertStringContainsString('<th>'.translate('messages.Provider').'</th>', $list,
            'the admin list column follows the module');
        $this->assertStringNotContainsString('<th>'.translate('messages.Store').'</th>', $list);

        $drawer = $this->actingAsAdmin()
            ->get(route('admin.bundle.view', $bundle->id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->getContent();

        $this->assertStringContainsString(translate('messages.Provider'), $drawer);
        $this->assertStringContainsString($this->store->name, $drawer,
            'and names the provider that owns it');
    }

    public function test_an_admin_creates_a_service_bundle_for_a_provider(): void
    {
        $services = $this->bookableServices();

        $response = $this->actingAsAdmin()->post(route('admin.bundle.store'), [
            'lang' => ['default'],
            'name' => ['Admin Service Combo'],
            'store_id' => $this->store->id,
            'start_date' => now()->addDay()->format('Y-m-d H:i'),
            'end_date' => now()->addMonth()->format('Y-m-d H:i'),
            'discount_percentage' => 15,
            'items' => [
                ['service_id' => $services[0]->id],
                ['service_id' => $services[1]->id],
            ],
            'image' => UploadedFile::fake()->image('admin.png'),
        ]);

        $this->assertSame(200, $response->getStatusCode(), $response->getContent());

        $bundle = Bundle::where('name', 'Admin Service Combo')->latest('id')->first();

        $this->assertNotNull($bundle);
        $this->assertSame('admin', $bundle->created_by);
        $this->assertCount(2, $bundle->items);

        foreach ($bundle->items as $line) {
            $this->assertTrue($line->isService());
        }
    }

    public function test_the_admin_export_downloads_for_a_service_module(): void
    {
        $this->serviceBundle();

        $response = $this->actingAsAdmin()->get(route('admin.bundle.export', ['type' => 'excel']));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_the_settings_page_offers_service_as_a_bundle_module(): void
    {
        $this->assertContains('service', BundleSettings::moduleTypes(),
            'the settings checkbox list is built from the same method');

        $this->assertTrue(BundleSettings::allowsModule((int) $this->store->module_id),
            'and ticking it lets the module through');

        $this->assertSame(translate('service'), BundleSettings::moduleTypeLabel('service'),
            'the checkbox is labelled, not left blank');

        $page = $this->actingAsAdmin()
            ->get(route('admin.business-settings.business-setup', ['tab' => 'order']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('product_bundle_modules[service]', $page,
            'without the checkbox an admin cannot switch bundles on for services at all');
        $this->assertStringContainsString('productBundleModule', $page);
    }

    private function bookableServices()
    {
        $services = Service::active(null, (int) $this->store->module_id)
            ->where('store_id', $this->store->id)->take(2)->get();

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
            'name' => 'Admin Panel Bundle',
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

    private function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin')->withSession([
            'current_module' => $this->store->module_id,
            'login_remember_token' => $this->admin->login_remember_token,
        ]);
    }
}
