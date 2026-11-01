<?php

namespace Tests\Unit;

use App\Models\SurgePrice;
use App\Services\Zone\SurgePriceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * S8 — the surge resolver, port doc §9.
 *
 * The tests that matter most here are the two type/emptiness cases: `module_ids` is written by
 * the admin screen as a json array of STRINGS while every caller passes an integer, and
 * `whereJsonContains` is type-sensitive. That mismatch had silently disabled a live surge.
 */
class SurgePriceServiceTest extends TestCase
{
    use DatabaseTransactions;

    private SurgePriceService $surge;

    private int $zoneId;

    private int $moduleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->surge = app(SurgePriceService::class);
        $this->surge->flushMemo();

        $row = \DB::table('module_zone')->first();

        if (! $row) {
            $this->markTestSkipped('needs a zone connected to a module');
        }

        $this->zoneId = (int) $row->zone_id;
        $this->moduleId = (int) $row->module_id;
    }

    /** A permanent weekly surge covering every weekday, so any test date matches. */
    private function permanent(mixed $moduleIds, array $overrides = []): SurgePrice
    {
        $surge = new SurgePrice;
        $surge->forceFill(array_merge([
            'surge_price_name' => 'Peak',
            'customer_note' => 'Busy right now.',
            'customer_note_status' => 1,
            'module_ids' => $moduleIds,
            'zone_id' => $this->zoneId,
            'price' => 10,
            'price_type' => 'percent',
            'status' => 1,
            'is_permanent' => 1,
            'duration_type' => 'weekly',
            'weekly_days' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
        ], $overrides));
        $surge->save();

        $this->surge->flushMemo();

        return $surge;
    }

    // ------------------------------------------------------------------ the live bug

    public function test_a_module_id_stored_as_a_string_still_matches_an_integer_caller(): void
    {
        // Exactly how the admin screen writes it: ["1", "2"].
        $this->permanent([(string) $this->moduleId]);

        $this->assertSame(10.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-09-06 10:00:00')['price']);
    }

    public function test_a_module_id_stored_as_an_integer_also_matches(): void
    {
        $this->permanent([$this->moduleId]);

        $this->assertSame(10.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-09-06 10:00:00')['price']);
    }

    public function test_an_empty_module_list_covers_every_module(): void
    {
        // §9.1 — rows predating the column keep working rather than covering nothing.
        $this->permanent([]);

        $this->assertSame(10.0, (float) $this->surge->resolve($this->zoneId, 999999, '2026-09-06 10:00:00')['price']);
    }

    public function test_a_module_outside_the_list_is_not_surged(): void
    {
        $this->permanent([(string) $this->moduleId]);

        $this->assertSame(0.0, (float) $this->surge->resolve($this->zoneId, 999999, '2026-09-06 10:00:00')['price']);
    }

    // ------------------------------------------------------------------ time and zone

    public function test_a_surge_outside_its_time_window_does_not_apply(): void
    {
        $this->permanent([(string) $this->moduleId], ['start_time' => '09:00:00', 'end_time' => '11:00:00']);

        $this->assertSame(10.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-09-06 10:00:00')['price']);
        $this->assertSame(0.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-09-06 08:59:00')['price']);
        $this->assertSame(0.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-09-06 11:01:00')['price']);
    }

    public function test_a_surge_on_a_weekday_it_does_not_cover_does_not_apply(): void
    {
        $this->permanent([(string) $this->moduleId], ['weekly_days' => ['Monday']]);

        $this->assertSame(10.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-09-07 10:00:00')['price']);
        $this->assertSame(0.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-09-06 10:00:00')['price']);
    }

    public function test_a_switched_off_surge_does_not_apply(): void
    {
        $this->permanent([(string) $this->moduleId], ['status' => 0]);

        $this->assertSame(0.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-09-06 10:00:00')['price']);
    }

    public function test_another_zone_is_not_surged(): void
    {
        $this->permanent([(string) $this->moduleId]);

        $this->assertSame(0.0, (float) $this->surge->resolve(999999, $this->moduleId, '2026-09-06 10:00:00')['price']);
    }

    public function test_nothing_configured_returns_the_empty_shape(): void
    {
        $empty = $this->surge->resolve($this->zoneId, $this->moduleId, '2026-09-06 10:00:00');

        $this->assertSame($this->surge->empty(), $empty);
        foreach (['title', 'customer_note', 'customer_note_status', 'price', 'price_type'] as $key) {
            $this->assertArrayHasKey($key, $empty);
        }
    }

    public function test_a_missing_zone_or_module_resolves_to_nothing(): void
    {
        $this->permanent([(string) $this->moduleId]);

        $this->assertSame(0.0, (float) $this->surge->resolve(null, $this->moduleId, '2026-09-06 10:00:00')['price']);
        $this->assertSame(0.0, (float) $this->surge->resolve($this->zoneId, null, '2026-09-06 10:00:00')['price']);
    }

    // ------------------------------------------------------------------ arithmetic and note

    public function test_apply_to_fee_uses_the_engines_own_arithmetic(): void
    {
        $percent = ['price' => 10, 'price_type' => 'percent', 'customer_note_status' => 0];
        $amount = ['price' => 7.5, 'price_type' => 'amount', 'customer_note_status' => 0];

        $this->assertSame(55.0, $this->surge->applyToFee(50.0, $percent));
        $this->assertSame(57.5, $this->surge->applyToFee(50.0, $amount));
    }

    public function test_nothing_is_surged_onto_a_free_delivery(): void
    {
        $this->assertSame(0.0, $this->surge->applyToFee(0.0, ['price' => 10, 'price_type' => 'percent']));
    }

    public function test_the_customer_note_is_shown_only_when_it_was_switched_on(): void
    {
        $on = ['price' => 10, 'customer_note' => 'Busy.', 'customer_note_status' => 1];
        $off = $on + [];
        $off['customer_note_status'] = 0;

        $this->assertSame('Busy.', $this->surge->customerNote($on, 50.0));
        $this->assertNull($this->surge->customerNote($off, 50.0));
    }

    public function test_the_customer_note_is_hidden_when_nothing_is_charged(): void
    {
        // §9.3 — nothing is surged onto a free delivery, so there is nothing to explain.
        $on = ['price' => 10, 'customer_note' => 'Busy.', 'customer_note_status' => 1];

        $this->assertNull($this->surge->customerNote($on, 0.0));
    }

    public function test_the_customer_note_is_hidden_when_nothing_is_surging(): void
    {
        $this->assertNull($this->surge->customerNote($this->surge->empty(), 50.0));
    }

    // ------------------------------------------------------------------ CRUD and G1

    private function dailyPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Probe',
            'customer_note' => 'Busy.',
            'customer_note_status' => true,
            'zone_id' => $this->zoneId,
            'module_ids' => [$this->moduleId],
            'price' => 12,
            'price_type' => 'percent',
            'duration_type' => 'daily',
            'is_permanent' => 0,
            'weekly_days' => [],
            'custom_days' => [],
            'custom_times' => [],
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-12',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
        ], $overrides);
    }

    public function test_saving_a_daily_surge_expands_one_row_per_day_and_module(): void
    {
        $surge = $this->surge->create($this->dailyPayload());

        $this->assertSame(3, \DB::table('surge_price_dates')->where('surge_price_id', $surge->id)->count());
        $this->assertSame(12.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-10-11 10:00:00')['price']);
        $this->assertSame(0.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-10-11 18:00:00')['price']);
        $this->assertSame(0.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-10-13 10:00:00')['price']);
    }

    public function test_editing_a_surge_rebuilds_its_expansion_rather_than_patching_it(): void
    {
        $surge = $this->surge->create($this->dailyPayload());

        $this->surge->update($surge->id, $this->dailyPayload(['end_date' => '2026-10-10']));

        // Not four rows, and not the old three: the expansion is rebuilt, so a day the admin
        // removed stops surging.
        $this->assertSame(1, \DB::table('surge_price_dates')->where('surge_price_id', $surge->id)->count());
        $this->assertSame(0.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-10-12 10:00:00')['price']);
    }

    public function test_a_permanent_weekly_surge_expands_no_dates(): void
    {
        $surge = $this->surge->create($this->dailyPayload([
            'duration_type' => 'weekly', 'is_permanent' => 1, 'weekly_days' => ['Sunday'],
            'start_date' => null, 'end_date' => null,
        ]));

        $this->assertSame(0, \DB::table('surge_price_dates')->where('surge_price_id', $surge->id)->count());
        $this->assertSame(12.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-10-11 10:00:00')['price']);
    }

    public function test_switching_schedule_type_clears_the_columns_the_other_type_used(): void
    {
        $surge = $this->surge->create($this->dailyPayload([
            'duration_type' => 'weekly', 'weekly_days' => ['Sunday'],
        ]));
        $this->assertNotEmpty($surge->weekly_days);

        $plain = $this->surge->update($surge->id, $this->dailyPayload());

        $this->assertNull($plain->weekly_days);
    }

    public function test_deleting_a_surge_takes_its_expansion_with_it(): void
    {
        $surge = $this->surge->create($this->dailyPayload());

        $this->assertTrue($this->surge->delete($surge->id));
        $this->assertSame(0, \DB::table('surge_price_dates')->where('surge_price_id', $surge->id)->count());
    }

    public function test_switching_a_surge_off_stops_it_resolving(): void
    {
        $surge = $this->surge->create($this->dailyPayload());

        $this->assertTrue($this->surge->updateStatus($surge->id, 0));
        $this->assertSame(0.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-10-11 10:00:00')['price']);
    }

    public function test_g1_refuses_an_overlapping_duration(): void
    {
        $this->surge->create($this->dailyPayload());

        $clashes = $this->surge->conflictingWindows($this->dailyPayload(['start_time' => '16:00:00', 'end_time' => '20:00:00']));

        $this->assertNotEmpty($clashes);
        $this->assertStringContainsString('Probe', $clashes[0]);
    }

    public function test_g1_allows_a_second_surge_on_the_same_zone_and_module_at_another_time(): void
    {
        $this->surge->create($this->dailyPayload());

        // NOT the D1/F1/E1 rule: a zone may be surged twice, just not over the same hours.
        $this->assertSame([], $this->surge->conflictingWindows(
            $this->dailyPayload(['start_time' => '18:00:00', 'end_time' => '22:00:00']),
        ));
    }

    public function test_g1_allows_the_same_hours_on_different_dates(): void
    {
        $this->surge->create($this->dailyPayload());

        $this->assertSame([], $this->surge->conflictingWindows(
            $this->dailyPayload(['start_date' => '2026-10-20', 'end_date' => '2026-10-22']),
        ));
    }

    public function test_g1_does_not_report_a_surge_clashing_with_itself(): void
    {
        $surge = $this->surge->create($this->dailyPayload());

        $this->assertSame([], $this->surge->conflictingWindows($this->dailyPayload(), $surge->id));
    }

    public function test_g1_reports_one_message_per_clashing_surge_not_per_day(): void
    {
        // A month-long overlap must not produce thirty identical sentences.
        $this->surge->create($this->dailyPayload(['start_date' => '2026-10-01', 'end_date' => '2026-10-31']));

        $clashes = $this->surge->conflictingWindows(
            $this->dailyPayload(['start_date' => '2026-10-01', 'end_date' => '2026-10-31']),
        );

        $this->assertCount(1, $clashes);
    }

    public function test_g1_refuses_a_dated_surge_that_a_permanent_weekly_one_already_covers(): void
    {
        $this->permanent([(string) $this->moduleId], ['weekly_days' => ['Sunday'], 'start_time' => '08:00:00', 'end_time' => '18:00:00']);

        // 11 Oct 2026 is a Sunday.
        $clashes = $this->surge->conflictingWindows($this->dailyPayload(['start_date' => '2026-10-11', 'end_date' => '2026-10-11']));

        $this->assertNotEmpty($clashes);
    }

    // ------------------------------------------------------------------ the memo (M8)

    public function test_the_same_question_is_only_asked_once(): void
    {
        $this->permanent([(string) $this->moduleId]);

        \DB::flushQueryLog();
        \DB::enableQueryLog();
        for ($i = 0; $i < 10; $i++) {
            $this->surge->resolve($this->zoneId, $this->moduleId, '2026-09-06 10:00:00');
        }
        $first = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        $this->assertLessThanOrEqual(3, $first, 'ten identical resolves must not issue ten lookups');
    }

    public function test_a_different_minute_is_a_different_question(): void
    {
        $this->permanent([(string) $this->moduleId], ['start_time' => '10:00:00', 'end_time' => '10:00:59']);

        $this->assertSame(10.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-09-06 10:00:30')['price']);
        $this->assertSame(0.0, (float) $this->surge->resolve($this->zoneId, $this->moduleId, '2026-09-06 10:01:30')['price']);
    }
}
