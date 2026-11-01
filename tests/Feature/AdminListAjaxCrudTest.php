<?php

namespace Tests\Feature;

use App\Http\Middleware\AjaxActionResponse;
use App\Models\Admin;
use App\Models\Brand;
use App\Models\StoreCategory;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminListAjaxCrudTest extends TestCase
{
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::find(1);
        $this->assertNotNull($this->admin, 'admin id 1 must exist');

        Storage::fake(config('filesystems.default'));
        Storage::fake('s3');
        Storage::fake('public');
    }

    private function panel(array $headers = [], string $referer = '/admin/unit')
    {
        return $this->actingAs($this->admin, 'admin')
            ->withSession([
                'login_remember_token' => $this->admin->login_remember_token,
                '_previous' => ['url' => url($referer)],
            ])
            ->withHeaders($headers + ['Referer' => url($referer)]);
    }

    private function ajax(): array
    {
        return [
            AjaxActionResponse::HEADER => '1',
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    private function assertIsRegionPage(string $html, string $formId): void
    {
        $this->assertStringContainsString('data-ajax-region', $html);
        $this->assertStringContainsString('data-ajax-forms', $html);
        $this->assertStringContainsString('id="'.$formId.'"', $html);
        $this->assertStringContainsString('data-ajax-remove="closest:tr"', $html);

        preg_match('/<form\b[^>]*\bid="'.preg_quote($formId, '/').'"[^>]*>/', $html, $m);
        $this->assertNotEmpty($m, 'the add form tag must be findable');
        $this->assertStringContainsString('data-ajax-form', $m[0]);
    }

    public function test_unit_page_ships_the_region_and_the_ajax_form(): void
    {
        $html = $this->panel()->get('/admin/unit')->assertOk()->getContent();
        $this->assertIsRegionPage($html, 'unit-add-form');
    }

    public function test_unit_create_and_delete_answer_json(): void
    {
        $name = 'ajaxunit-'.uniqid();

        $create = $this->panel($this->ajax())->post('/admin/unit/store', [
            'unit' => [$name],
            'lang' => ['default'],
        ]);

        $create->assertOk();
        $create->assertJson(['ok' => true, 'type' => 'success']);

        $unit = Unit::withoutGlobalScopes()->where('unit', $name)->first();
        $this->assertNotNull($unit);

        $delete = $this->panel($this->ajax())->delete('/admin/unit/delete/'.$unit->id);
        $delete->assertOk();
        $delete->assertJson(['ok' => true]);
        $this->assertNull(Unit::withoutGlobalScopes()->where('unit', $name)->first());
    }

    public function test_unit_create_reports_validation_errors_per_field(): void
    {
        $response = $this->panel($this->ajax())->post('/admin/unit/store', [
            'unit' => [''],
            'lang' => ['default'],
        ]);

        $response->assertStatus(422);
        $response->assertJson(['ok' => false]);
        $this->assertArrayHasKey('unit.0', $response->json('errors'));
    }

    public function test_store_category_page_ships_the_region_and_the_ajax_form(): void
    {
        $html = $this->panel([], '/admin/store-category/list')
            ->get('/admin/store-category/list')->assertOk()->getContent();

        $this->assertIsRegionPage($html, 'store-category-add-form');
    }

    public function test_store_category_create_and_delete_answer_json(): void
    {
        $storeId = DB::table('stores')->value('id');

        if (! $storeId) {
            $this->markTestSkipped('no store to attach a store category to');
        }

        $name = 'ajaxstorecat-'.uniqid();

        $create = $this->panel($this->ajax(), '/admin/store-category/list')
            ->post('/admin/store-category/store', [
                'name' => [$name],
                'lang' => ['default'],
                'store_id' => $storeId,
                'priority' => 0,
                'image' => UploadedFile::fake()->image('sc.png', 80, 80),
            ]);

        $create->assertOk();
        $create->assertJson(['ok' => true, 'type' => 'success']);

        $row = StoreCategory::withoutGlobalScopes()->where('name', $name)->first();
        $this->assertNotNull($row);

        $delete = $this->panel($this->ajax(), '/admin/store-category/list')
            ->delete('/admin/store-category/delete', ['id' => $row->id]);

        $delete->assertOk();
        $delete->assertJson(['ok' => true]);
        $this->assertNull(StoreCategory::withoutGlobalScopes()->find($row->id));
    }

    public function test_brand_page_ships_the_region_and_the_ajax_form(): void
    {
        $html = $this->panel([], '/admin/brand?module_id=1')
            ->get('/admin/brand?module_id=1')->assertOk()->getContent();

        $this->assertIsRegionPage($html, 'brand-add-form');
    }

    public function test_brand_create_and_delete_answer_json(): void
    {
        $name = 'ajaxbrand-'.uniqid();

        $create = $this->panel($this->ajax(), '/admin/brand?module_id=1')
            ->post('/admin/brand/store?module_id=1', [
                'name' => [$name],
                'lang' => ['default'],
                'image' => UploadedFile::fake()->image('b.png', 80, 80),
            ]);

        $create->assertOk();
        $create->assertJson(['ok' => true, 'type' => 'success']);

        $brand = Brand::withoutGlobalScopes()->where('name', $name)->first();
        $this->assertNotNull($brand);

        $delete = $this->panel($this->ajax(), '/admin/brand?module_id=1')
            ->delete('/admin/brand/delete/'.$brand->id.'?module_id=1');

        $delete->assertOk();
        $delete->assertJson(['ok' => true]);
        $this->assertNull(Brand::withoutGlobalScopes()->find($brand->id));
    }

    public function test_brand_update_answers_json_from_the_sidebar_form(): void
    {
        $name = 'ajaxbrand-'.uniqid();

        $this->panel([], '/admin/brand?module_id=1')->post('/admin/brand/store?module_id=1', [
            'name' => [$name],
            'lang' => ['default'],
            'image' => UploadedFile::fake()->image('b.png', 80, 80),
        ]);

        $brand = Brand::withoutGlobalScopes()->where('name', $name)->first();
        $this->assertNotNull($brand);

        $renamed = $name.'-edited';

        $update = $this->panel($this->ajax(), '/admin/brand?module_id=1')
            ->post('/admin/brand/edit/'.$brand->id.'?module_id=1', [
                'name' => [$renamed],
                'lang' => ['default'],
            ]);

        $update->assertOk();
        $update->assertJson(['ok' => true, 'type' => 'success']);
        $this->assertSame($renamed, Brand::withoutGlobalScopes()->find($brand->id)->name);

        Brand::withoutGlobalScopes()->where('id', $brand->id)->forceDelete();
    }

    public function test_store_category_update_answers_json_from_the_offcanvas_form(): void
    {
        $storeId = DB::table('stores')->value('id');

        if (! $storeId) {
            $this->markTestSkipped('no store to attach a store category to');
        }

        $name = 'ajaxstorecat-'.uniqid();

        $this->panel([], '/admin/store-category/list')->post('/admin/store-category/store', [
            'name' => [$name],
            'lang' => ['default'],
            'store_id' => $storeId,
            'priority' => 0,
            'image' => UploadedFile::fake()->image('sc.png', 80, 80),
        ]);

        $row = StoreCategory::withoutGlobalScopes()->where('name', $name)->first();
        $this->assertNotNull($row);

        $renamed = $name.'-edited';

        $update = $this->panel($this->ajax(), '/admin/store-category/list')
            ->post('/admin/store-category/update/'.$row->id, [
                'name' => [$renamed],
                'lang' => ['default'],
                'store_id' => $storeId,
                'priority' => 0,
            ]);

        $update->assertOk();
        $update->assertJson(['ok' => true, 'type' => 'success']);
        $this->assertSame($renamed, StoreCategory::withoutGlobalScopes()->find($row->id)->name);

        StoreCategory::withoutGlobalScopes()->where('id', $row->id)->forceDelete();
    }

    public function test_a_fragment_request_returns_a_page_the_region_can_be_cut_from(): void
    {
        $body = $this->panel([
            AjaxActionResponse::FRAGMENT_HEADER => '[data-ajax-region]',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get('/admin/unit')->assertOk()->getContent();

        $this->assertStringContainsString('data-ajax-region', $body);
    }

    public function test_status_toggle_can_flip_a_query_parameter_state(): void
    {
        $source = file_get_contents(public_path('assets/admin/js/status-toggle.js'));

        $this->assertStringContainsString(
            '[?&]status=',
            $source,
            'store-category status URLs carry the state in a query parameter, not a path segment. '
            .'Without this branch flipUrl returns null, the switch keeps the old data-url and the '
            .'second click re-sends the state it already sent, so the row silently stops matching '
            .'what the admin sees.'
        );
    }

    public function test_the_layer_defers_to_an_earlier_submit_veto(): void
    {
        $source = file_get_contents(public_path('assets/admin/js/ajax-framework.js'));

        $this->assertStringContainsString(
            'event.isDefaultPrevented()',
            $source,
            'the brand screens validate in their own delegated submit handlers and preventDefault '
            .'on failure; returning false there stops propagation but not other handlers bound to '
            .'document, so without this guard an invalid form would still be posted over ajax.'
        );
    }
}
