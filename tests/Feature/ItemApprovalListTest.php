<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Admin;
use App\Models\TempProduct;
use App\Scopes\StoreScope;
use App\Services\Item\TempProductService;
use Tests\TestCase;

class ItemApprovalListTest extends TestCase
{
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::find(1);
        $this->assertNotNull($this->admin, 'admin id 1 must exist');

        if (Helpers::get_business_settings('product_approval') != 1) {
            $this->markTestSkipped('product approval is switched off');
        }
    }

    private function panel()
    {
        return $this->actingAs($this->admin, 'admin')
            ->withSession([
                'login_remember_token' => $this->admin->login_remember_token,
                '_previous' => ['url' => url('/admin/item/new/item/list')],
            ])
            ->withHeaders(['Referer' => url('/admin/item/new/item/list')]);
    }

    private function moduleId(): int
    {
        return (int) TempProduct::withoutGlobalScope(StoreScope::class)->value('module_id') ?: 1;
    }

    public function test_the_page_ships_the_region_the_summary_and_the_filter_drawer(): void
    {
        $html = $this->panel()->get('/admin/item/new/item/list?module_id='.$this->moduleId())->assertOk()->getContent();

        $this->assertStringContainsString('data-ajax-region', $html);
        $this->assertStringContainsString('data-ajax-forms=".search-form"', $html);
        $this->assertStringContainsString('itm-summary', $html);
        $this->assertStringContainsString('itm-tile', $html);
        $this->assertStringContainsString('id="datatableFilterSidebar"', $html);
        $this->assertStringContainsString('name="status[]"', $html);
        $this->assertStringContainsString('name="kind[]"', $html);
    }

    public function test_a_facet_filter_narrows_the_list_but_not_the_summary(): void
    {
        $service = app(TempProductService::class);
        $module_id = $this->moduleId();

        $unfiltered = $service->adminApprovalSummary($service->adminApprovalFilters(['module_id' => $module_id]));

        foreach ([['status' => ['rejected']], ['kind' => ['new']]] as $facet) {
            $summary = $service->adminApprovalSummary($service->adminApprovalFilters(['module_id' => $module_id] + $facet));
            $this->assertSame($unfiltered, $summary, 'the summary stays navigable while a facet is on');
        }

        foreach ([['status' => ['rejected'], 'key' => 'rejected'], ['kind' => ['new'], 'key' => 'new']] as $facet) {
            $key = $facet['key'];
            unset($facet['key']);

            $filters = $service->adminApprovalFilters(['module_id' => $module_id] + $facet);
            $this->assertSame(
                $unfiltered[$key],
                $service->adminApprovalList($filters, ['page' => 1])->total(),
                'the list carries the facet filter'
            );
        }
    }

    public function test_a_status_or_kind_group_with_both_boxes_ticked_narrows_nothing(): void
    {
        $service = app(TempProductService::class);
        $module_id = $this->moduleId();

        $summary = $service->adminApprovalSummary($service->adminApprovalFilters(['module_id' => $module_id]));

        foreach ([['status' => ['pending', 'rejected']], ['kind' => ['new', 'update']]] as $group) {
            $filters = $service->adminApprovalFilters(['module_id' => $module_id] + $group);
            $this->assertSame($summary['total'], $service->adminApprovalList($filters, ['page' => 1])->total());
        }
    }

    public function test_an_unknown_facet_value_is_dropped_rather_than_reaching_the_query(): void
    {
        $filters = app(TempProductService::class)->adminApprovalFilters([
            'module_id' => $this->moduleId(),
            'store_id' => 'all',
            'category_id' => "1 or '1",
            'status' => ['pending', 'approved'],
            'kind' => 'update',
            'type' => ['veg', 'halal'],
            'from_date' => '12-01-2026',
            'to_date' => '2026-01-12',
        ]);

        $this->assertNull($filters['store_id']);
        $this->assertNull($filters['category_id']);
        $this->assertSame(['pending'], $filters['status']);
        $this->assertSame(['update'], $filters['kind']);
        $this->assertSame(['veg'], $filters['type']);
        $this->assertNull($filters['from_date']);
        $this->assertSame('2026-01-12', $filters['to_date']);
    }

    public function test_export_answers_the_same_filters_as_the_screen(): void
    {
        $service = app(TempProductService::class);
        $filters = $service->adminApprovalFilters(['module_id' => $this->moduleId(), 'status' => ['pending']]);

        $this->assertSame(
            $service->adminApprovalList($filters, ['page' => 1])->total(),
            $service->adminApprovalExportQuery($filters)->count()
        );
    }
}
