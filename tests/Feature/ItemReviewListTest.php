<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Review;
use App\Services\Item\ReviewService;
use Tests\TestCase;

class ItemReviewListTest extends TestCase
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
                '_previous' => ['url' => url('/admin/item/reviews')],
            ])
            ->withHeaders(['Referer' => url('/admin/item/reviews')]);
    }

    private function moduleId(): int
    {
        return (int) Review::query()
            ->whereHas('item')
            ->value('module_id') ?: 1;
    }

    public function test_the_page_ships_the_region_the_summary_and_the_filter_drawer(): void
    {
        $html = $this->panel()->get('/admin/item/reviews?module_id='.$this->moduleId())->assertOk()->getContent();

        $this->assertStringContainsString('data-ajax-region', $html);
        $this->assertStringContainsString('data-ajax-forms=".search-form"', $html);
        $this->assertStringContainsString('rvw-summary', $html);
        $this->assertStringContainsString('rvw-bar__fill', $html);
        $this->assertStringContainsString('id="datatableFilterSidebar"', $html);
        $this->assertStringContainsString('name="rating[]"', $html);
    }

    public function test_a_facet_filter_narrows_the_list_but_not_the_summary(): void
    {
        $service = app(ReviewService::class);
        $module_id = $this->moduleId();

        $unfiltered = $service->adminSummary($service->adminFilters(['module_id' => $module_id]));

        foreach ([['rating' => [5]], ['visibility' => ['hidden']], ['reply' => ['awaiting']]] as $facet) {
            $summary = $service->adminSummary($service->adminFilters(['module_id' => $module_id] + $facet));
            $this->assertSame($unfiltered, $summary, 'the summary stays navigable while a facet is on');
        }

        $filters = $service->adminFilters(['module_id' => $module_id, 'rating' => [5]]);
        $this->assertSame(
            $unfiltered['breakdown'][5]['count'],
            $service->adminList($filters, ['page' => 1])->total(),
            'the list carries the rating filter'
        );
    }

    public function test_the_visibility_and_reply_filters_are_only_applied_when_they_narrow_something(): void
    {
        $service = app(ReviewService::class);
        $module_id = $this->moduleId();

        $summary = $service->adminSummary($service->adminFilters(['module_id' => $module_id]));

        $both = $service->adminFilters(['module_id' => $module_id, 'visibility' => ['visible', 'hidden']]);
        $this->assertSame($summary['total'], $service->adminList($both, ['page' => 1])->total());

        $hidden = $service->adminFilters(['module_id' => $module_id, 'visibility' => ['hidden']]);
        $this->assertSame($summary['hidden'], $service->adminList($hidden, ['page' => 1])->total());

        $awaiting = $service->adminFilters(['module_id' => $module_id, 'reply' => ['awaiting']]);
        $this->assertSame($summary['awaiting'], $service->adminList($awaiting, ['page' => 1])->total());
    }

    public function test_a_bad_date_filter_is_dropped_rather_than_reaching_the_query(): void
    {
        $filters = app(ReviewService::class)->adminFilters([
            'module_id' => $this->moduleId(),
            'from_date' => "2026-01-01' or '1",
            'to_date' => '2026-01-31',
        ]);

        $this->assertNull($filters['from_date']);
        $this->assertSame('2026-01-31', $filters['to_date']);
    }
}
