<?php

namespace Tests\Feature;

use App\Traits\Report\ReportGeneratorTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * TC_145 / TC_158 — the vendor (and admin) Store Expense Breakdown widget/chart only rendered 5
 * fixed categories (Commission, Subscription, Discount On Item, Coupon, Free Delivery), silently
 * omitting bogo_discount / happy_hour_discount / bundle_discount even though
 * getStoreEarningSummaryData() already computes all three as separate breakdown keys — the
 * controller passed them through, the blade just never rendered them. Shared partial
 * (_store-expense-breakdown.blade.php) used by both Admin\StoreEarningReportController and
 * Vendor\StoreEarningReportController, so one fix closes the gap on both panels.
 */
class StoreExpenseBreakdownPromotionTest extends TestCase
{
    use DatabaseTransactions;
    use ReportGeneratorTrait;

    public function test_bogo_happy_hour_and_bundle_discount_render_in_the_breakdown(): void
    {
        $store = \App\Models\Store::where('status', 1)->first();

        if (! $store) {
            $this->markTestSkipped('need an active store');
        }

        $summary = $this->getStoreEarningSummaryData($store->id, 'all_time', null, null);

        $html = View::make('admin-views.report.partials._store-expense-breakdown', compact('summary'))->render();

        $this->assertStringContainsString('BOGO Discount', $html);
        $this->assertStringContainsString('Happy Hour Discount', $html);
        $this->assertStringContainsString('Bundle Discount', $html);
    }
}
