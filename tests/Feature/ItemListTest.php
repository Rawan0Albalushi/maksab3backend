<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Item;
use App\Services\Item\ItemService;
use Tests\TestCase;

class ItemListTest extends TestCase
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
                '_previous' => ['url' => url('/admin/item/list')],
            ])
            ->withHeaders(['Referer' => url('/admin/item/list')]);
    }

    private function moduleId(): int
    {
        return (int) Item::query()->where('is_approved', 1)->value('module_id') ?: 1;
    }

    public function test_the_page_ships_the_region_the_summary_and_the_filter_drawer(): void
    {
        $html = $this->panel()->get('/admin/item/list?module_id='.$this->moduleId())->assertOk()->getContent();

        $this->assertStringContainsString('data-ajax-region', $html);
        $this->assertStringContainsString('data-ajax-forms=".search-form"', $html);
        $this->assertStringContainsString('itm-summary', $html);
        $this->assertStringContainsString('itm-tile', $html);
        $this->assertStringContainsString('id="datatableFilterSidebar"', $html);
        $this->assertStringContainsString('name="status[]"', $html);
        $this->assertStringContainsString('name="flag[]"', $html);
    }

    public function test_a_facet_filter_narrows_the_list_but_not_the_summary(): void
    {
        $service = app(ItemService::class);
        $module_id = $this->moduleId();

        $unfiltered = $service->adminListSummary($service->adminListFilters(['module_id' => $module_id]));

        foreach ([['status' => ['inactive']], ['stock' => ['out']], ['flag' => ['discounted']]] as $facet) {
            $summary = $service->adminListSummary($service->adminListFilters(['module_id' => $module_id] + $facet));
            $this->assertSame($unfiltered, $summary, 'the summary stays navigable while a facet is on');
        }

        $filters = $service->adminListFilters(['module_id' => $module_id, 'status' => ['inactive']]);
        $this->assertSame(
            $unfiltered['inactive'],
            $service->adminList($filters, ['page' => 1])->total(),
            'the list carries the status filter'
        );
    }

    public function test_a_status_or_stock_group_with_both_boxes_ticked_narrows_nothing(): void
    {
        $service = app(ItemService::class);
        $module_id = $this->moduleId();

        $summary = $service->adminListSummary($service->adminListFilters(['module_id' => $module_id]));

        foreach ([['status' => ['active', 'inactive']], ['stock' => ['in', 'out']]] as $group) {
            $filters = $service->adminListFilters(['module_id' => $module_id] + $group);
            $this->assertSame($summary['total'], $service->adminList($filters, ['page' => 1])->total());
        }

        $out = $service->adminListFilters(['module_id' => $module_id, 'stock' => ['out']]);
        $this->assertSame($summary['out_of_stock'], $service->adminList($out, ['page' => 1])->total());
    }

    public function test_an_unknown_facet_value_is_dropped_rather_than_reaching_the_query(): void
    {
        $filters = app(ItemService::class)->adminListFilters([
            'module_id' => $this->moduleId(),
            'store_id' => 'all',
            'category_id' => "1 or '1",
            'status' => ['active', 'deleted'],
            'stock' => 'out',
            'flag' => ['discounted', 'featured'],
        ]);

        $this->assertNull($filters['store_id']);
        $this->assertNull($filters['category_id']);
        $this->assertSame(['active'], $filters['status']);
        $this->assertSame(['out'], $filters['stock']);
        $this->assertSame(['discounted'], $filters['flag']);
    }

    public function test_export_answers_the_same_filters_as_the_screen(): void
    {
        $service = app(ItemService::class);
        $filters = $service->adminListFilters(['module_id' => $this->moduleId(), 'status' => ['inactive']]);

        $this->assertSame(
            $service->adminList($filters, ['page' => 1])->total(),
            $service->adminListExportQuery($filters)->count()
        );
    }
}
