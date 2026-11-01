<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Traits\Report\ReportGeneratorTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * TC_86 (vendor Bundle QA) — chasing "no orphan bundle expense entry is left in the report" found
 * a bigger, platform-wide gap: Order::scopeNotRefunded() already excludes a refunded order from
 * every EARNING figure (joined through order_transactions), but nothing excluded its EXPENSE rows
 * (discount_on_product, bundle_discount, happy_hour_discount, bogo_discount, ...) — those are
 * written once at delivery and never reversed, so refunding an order left its expense permanently
 * counted in "Total Expenses" while its matching revenue correctly disappeared from earnings.
 * Fixed with Expense::scopeNotRefunded(), applied at every Expense query site across the admin and
 * vendor report controllers (order-linked rows only — trip/ride/service-booking expenses have no
 * refunded concept in this codebase, so the scope is a no-op there by construction).
 */
class RefundedOrderExpenseExclusionTest extends TestCase
{
    use DatabaseTransactions;
    use ReportGeneratorTrait;

    public function test_a_refunded_orders_expense_row_survives_but_stops_counting(): void
    {
        $expense = Expense::where('created_by', 'vendor')
            ->whereNotNull('order_id')
            ->where('amount', '>', 0)
            ->with('order')
            ->first();

        if (! $expense || ! $expense->order) {
            $this->markTestSkipped('need an existing vendor expense row linked to a real order');
        }

        $order = $expense->order;
        $storeId = $expense->store_id;
        $type = $expense->type;

        $before = Expense::where('store_id', $storeId)->where('created_by', 'vendor')
            ->notRefunded()->where('type', $type)->sum('amount');
        $summaryBefore = $this->getStoreEarningSummaryData($storeId, 'all_time', null, null);

        $order->order_status = 'refunded';
        $order->save();

        $after = Expense::where('store_id', $storeId)->where('created_by', 'vendor')
            ->notRefunded()->where('type', $type)->sum('amount');
        $summaryAfter = $this->getStoreEarningSummaryData($storeId, 'all_time', null, null);

        $stillInTable = Expense::where('id', $expense->id)->exists();

        $this->assertTrue($stillInTable, 'the expense row itself must not be deleted, only excluded from aggregates');
        $this->assertEqualsWithDelta($before - (float) $expense->amount, $after, 0.01, 'a refunded order\'s expense must drop out of the total once refunded');
        $this->assertEqualsWithDelta(
            $summaryBefore['total_expenses'] - (float) $expense->amount,
            $summaryAfter['total_expenses'],
            0.01,
            'getStoreEarningSummaryData() total_expenses must drop by exactly the refunded expense amount'
        );
    }
}
