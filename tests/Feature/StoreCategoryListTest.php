<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\StoreCategory;
use App\Services\Store\StoreCategoryService;
use Tests\TestCase;

class StoreCategoryListTest extends TestCase
{
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::find(1);
        $this->assertNotNull($this->admin, 'admin id 1 must exist');
    }

    private function panel()
    {
        return $this->actingAs($this->admin, 'admin')
            ->withSession([
                'login_remember_token' => $this->admin->login_remember_token,
                '_previous' => ['url' => url('/admin/store-category/list')],
            ])
            ->withHeaders(['Referer' => url('/admin/store-category/list')]);
    }

    private function moduleId(): int
    {
        return (int) StoreCategory::query()->value('module_id') ?: 1;
    }

    public function test_the_page_ships_the_region_the_summary_and_the_filter_drawer(): void
    {
        $html = $this->panel()->get('/admin/store-category/list?module_id='.$this->moduleId())->assertOk()->getContent();

        $this->assertStringContainsString('data-ajax-region', $html);
        $this->assertStringContainsString('data-ajax-forms=".search-form"', $html);
        $this->assertStringContainsString('stc-summary', $html);
        $this->assertStringContainsString('stc-tile', $html);
        $this->assertStringContainsString('id="datatableFilterSidebar"', $html);
        $this->assertStringContainsString('name="usage"', $html);
    }

    public function test_the_table_leads_with_the_category_not_a_serial_column(): void
    {
        $html = $this->panel()->get('/admin/store-category/list?module_id='.$this->moduleId())->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<thead[^>]*>\s*<tr>\s*<th[^>]*>\s*Category\s*<\/th>/',
            $html,
            'the first column is the category itself — the SL column was dropped'
        );
        $this->assertStringContainsString('>Items<', $html);
        $this->assertStringContainsString('>Translations<', $html);
    }

    public function test_a_facet_filter_narrows_the_list_but_not_the_summary(): void
    {
        $service = app(StoreCategoryService::class);
        $module_id = $this->moduleId();

        $unfiltered = $service->adminSummary($service->adminListFilters(['module_id' => $module_id]));

        foreach ([['status' => '0'], ['priority' => '2'], ['usage' => 'empty']] as $facet) {
            $summary = $service->adminSummary($service->adminListFilters(['module_id' => $module_id] + $facet));
            $this->assertSame($unfiltered, $summary, 'the summary stays navigable while a facet is on');
        }

        foreach (['status' => ['0', 'inactive'], 'usage' => ['empty', 'empty']] as $key => [$value, $summaryKey]) {
            $filters = $service->adminListFilters(['module_id' => $module_id, $key => $value]);
            $this->assertSame(
                $unfiltered[$summaryKey],
                $service->buildQuery($filters)->count(),
                'the list carries the '.$key.' filter'
            );
        }
    }

    public function test_an_unknown_filter_value_is_dropped_rather_than_reaching_the_query(): void
    {
        $filters = app(StoreCategoryService::class)->adminListFilters([
            'module_id' => $this->moduleId(),
            'store_id' => 'all',
            'priority' => "1 or '1",
            'status' => 'active',
            'usage' => 'anything',
        ]);

        $this->assertNull($filters['store_id']);
        $this->assertNull($filters['priority']);
        $this->assertNull($filters['status']);
        $this->assertNull($filters['usage']);
    }

    public function test_the_open_drawer_only_lifts_the_detached_select2_dropdown(): void
    {
        $css = file_get_contents(public_path('assets/admin/css/filter-drawer.css'));

        $this->assertStringContainsString(
            'body.fd-open > .select2-container',
            $css,
            'select2 appends its dropdown as a direct child of <body>, and only that container may '
            .'sit above the backdrop. As a descendant selector this rule also lifted every inline '
            .'select2 on the page behind the drawer, which then painted over the dim.'
        );
        $this->assertStringNotContainsString('body.fd-open .select2-container', $css);
    }

    public function test_a_normal_priority_filter_survives_the_zero_it_is_written_with(): void
    {
        $filters = app(StoreCategoryService::class)->adminListFilters([
            'module_id' => $this->moduleId(),
            'priority' => '0',
        ]);

        $this->assertSame(0, $filters['priority']);
        $this->assertSame(1, app(StoreCategoryService::class)->adminListFilterCount($filters));
    }
}
