<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Zone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The Order Transaction Report reuses one query builder twice: once to paginate the rows, and
 * once -- with `join('orders as o', ...)` -- to total the earnings. `zone_id`, `module_id` and
 * `created_at` all exist on BOTH order_transactions and orders, so an unqualified reference
 * inside that builder makes MySQL reject the second query outright and the whole screen 500s
 * the moment a zone, module or date filter is applied.
 *
 * This asserts the three filters render, together and apart, on the page and on the export.
 */
class ReportZoneFilterTest extends TestCase
{
    use DatabaseTransactions;

    private const PAGE = 'admin.transactions.report.day-wise-report';

    private const EXPORT = 'admin.transactions.report.day-wise-report-export';

    public function test_transaction_report_renders_with_zone_module_and_date_filters(): void
    {
        $admin = Admin::find(1);
        $this->assertNotNull($admin, 'admin id 1 must exist');

        $zoneId = Zone::query()->value('id');
        $this->assertNotNull($zoneId, 'at least one zone must exist');

        $queries = [
            'zone_id='.$zoneId,
            'module_id=1',
            'filter=this_month',
            'zone_id='.$zoneId.'&module_id=1&filter=this_year',
        ];

        foreach ($queries as $query) {
            $this->withoutExceptionHandling();

            $this->actingAs($admin, 'admin')
                ->withSession(['login_remember_token' => $admin->login_remember_token])
                ->get(route(self::PAGE).'?'.$query)
                ->assertOk();
        }
    }

    public function test_transaction_report_export_survives_the_same_filters(): void
    {
        $admin = Admin::find(1);
        $zoneId = Zone::query()->value('id');

        $this->withoutExceptionHandling();

        $this->actingAs($admin, 'admin')
            ->withSession(['login_remember_token' => $admin->login_remember_token])
            ->get(route(self::EXPORT).'?type=csv&zone_id='.$zoneId.'&filter=this_year')
            ->assertOk();
    }

    /**
     * The filter must narrow the result set, not merely avoid crashing: a zone that owns no
     * transaction returns none, which a filter silently ignored would not do.
     */
    public function test_zone_filter_actually_narrows_the_transaction_list(): void
    {
        $admin = Admin::find(1);

        $empty = Zone::query()
            ->whereNotExists(fn ($q) => $q->selectRaw(1)
                ->from('order_transactions')
                ->whereColumn('order_transactions.zone_id', 'zones.id'))
            ->value('id');

        if (! $empty) {
            $this->markTestSkipped('every zone owns transactions; no empty zone to filter by');
        }

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['login_remember_token' => $admin->login_remember_token])
            ->get(route(self::PAGE).'?zone_id='.$empty.'&filter=all_time')
            ->assertOk();

        $this->assertStringNotContainsString('/admin/order/details/', $response->getContent());
    }
}
