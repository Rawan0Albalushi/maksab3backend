<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Http\UploadedFile;
use Modules\RideShare\Entities\VehicleManagement\RiderVehicleBrand;
use Tests\TestCase;

class RiderVehicleBrandAjaxCrudTest extends TestCase
{
    private function actor()
    {
        $admin = Admin::find(1);

        if (! $admin) {
            $this->markTestSkipped('admin id 1 must exist');
        }

        return $this->actingAs($admin, 'admin')
            ->withSession(['login_remember_token' => $admin->login_remember_token]);
    }

    private function page(string $uri)
    {
        $response = $this->actor()->get($uri);

        if ($response->getStatusCode() !== 200) {
            $this->markTestSkipped('ride_vehicle module is not enabled: '.$response->getStatusCode());
        }

        return $response->getContent();
    }

    public function test_the_list_page_ships_the_region_and_the_opted_in_forms(): void
    {
        $html = $this->page('admin/users/rider/vehicle/brand');

        $this->assertStringContainsString('id="brand-list-wrapper"', $html);
        $this->assertStringContainsString('data-ajax-region', $html);
        $this->assertStringContainsString('id="brand-add-form"', $html);
        $this->assertStringContainsString('data-ajax-refresh="[data-ajax-region]"', $html);
        $this->assertStringContainsString('data-ajax-reset', $html);
    }

    public function test_the_delete_form_and_the_list_controls_sit_inside_the_region(): void
    {
        $html = $this->page('admin/users/rider/vehicle/brand');

        $region = substr($html, strpos($html, 'id="brand-list-wrapper"'));

        $this->assertStringContainsString('data-ajax-links=".nav-link, .page-link, .list-reset-search"', $html);
        $this->assertStringContainsString('data-ajax-forms=".search-form"', $html);

        if (RiderVehicleBrand::query()->exists()) {
            $this->assertStringContainsString('data-ajax-remove="closest:tr"', $region);
        }
    }

    public function test_the_status_switch_asks_for_a_refresh_only_on_a_filtered_tab(): void
    {
        if (! RiderVehicleBrand::query()->exists()) {
            $this->markTestSkipped('a rider vehicle brand must exist');
        }

        foreach ($this->statusSwitches($this->page('admin/users/rider/vehicle/brand?status=all')) as $target) {
            $this->assertSame('', $target);
        }

        foreach ($this->statusSwitches($this->page('admin/users/rider/vehicle/brand?status=active')) as $target) {
            $this->assertSame('[data-ajax-region]', $target);
        }
    }

    private function statusSwitches(string $html): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $refreshTargets = [];

        foreach ((new \DOMXPath($dom))->query('//input[contains(@class, "dynamic-checkbox")]') as $switch) {
            $refreshTargets[] = $switch->getAttribute('data-ajax-refresh');
        }

        $this->assertNotEmpty($refreshTargets);

        return $refreshTargets;
    }

    public function test_the_edit_page_opts_its_form_in(): void
    {
        $brand = RiderVehicleBrand::query()->first();

        if (! $brand) {
            $this->markTestSkipped('a rider vehicle brand must exist');
        }

        $html = $this->page('admin/users/rider/vehicle/brand/edit/'.$brand->id);

        $this->assertStringContainsString('id="brand-edit-form"', $html);
        $this->assertStringContainsString('data-ajax-form', $html);
    }

    public function test_an_add_answers_json_and_a_refused_one_reports_its_errors(): void
    {
        $this->page('admin/users/rider/vehicle/brand');

        $name = 'zz-test-brand-'.uniqid();

        $this->actor()->withHeaders(['X-Ajax-Request' => '1'])
            ->post('admin/users/rider/vehicle/brand/store', [
                'brand_name' => [$name],
                'short_desc' => ['created by RiderVehicleBrandAjaxCrudTest'],
                'lang' => ['default'],
                'brand_logo' => UploadedFile::fake()->image('brand.png', 120, 120),
            ])
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

        $created = RiderVehicleBrand::query()->where('name', $name)->first();
        $this->assertNotNull($created);

        $this->actor()->withHeaders(['X-Ajax-Request' => '1'])
            ->post('admin/users/rider/vehicle/brand/store', [
                'brand_name' => [''],
                'short_desc' => [''],
                'lang' => ['default'],
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false])
            ->assertJsonStructure(['errors' => ['brand_name.0']]);

        $this->actor()->withHeaders(['X-Ajax-Request' => '1'])
            ->postJson('admin/users/rider/vehicle/brand/delete/'.$created->id, ['_method' => 'delete'])
            ->assertStatus(200);

        RiderVehicleBrand::withTrashed()->find($created->id)?->forceDelete();
    }

    public function test_a_delete_answers_json_and_the_row_is_removed(): void
    {
        $this->page('admin/users/rider/vehicle/brand');

        $brand = RiderVehicleBrand::query()->create([
            'name' => 'zz-test-brand-'.uniqid(),
            'description' => 'created by RiderVehicleBrandAjaxCrudTest',
            'image' => '',
            'is_active' => 1,
        ]);

        $this->actor()->withHeaders(['X-Ajax-Request' => '1'])
            ->postJson('admin/users/rider/vehicle/brand/delete/'.$brand->id, ['_method' => 'delete'])
            ->assertStatus(200)
            ->assertJson(['ok' => true]);

        $this->assertNull(RiderVehicleBrand::query()->find($brand->id));

        RiderVehicleBrand::withTrashed()->find($brand->id)?->forceDelete();
    }
}
