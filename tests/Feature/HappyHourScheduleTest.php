<?php

namespace Tests\Feature;

use App\Models\HappyHour;
use App\Models\HappyHourDate;
use App\Models\Store;
use App\Services\Promotion\HappyHourScheduleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Schedule expansion and the overlap rule.
 *
 * The overlap rule is what makes "the one happy hour running here" a safe assumption everywhere
 * else -- the catalog returns a single happy hour rather than a list precisely because two
 * cannot overlap in one module.
 */
class HappyHourScheduleTest extends TestCase
{
    use DatabaseTransactions;

    private HappyHourScheduleService $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schedule = app(HappyHourScheduleService::class);
    }

    /**
     * The module and nothing else -- exactly the set the overlap check reads. A broader key would
     * serialise saves that cannot conflict; a narrower one would not protect the query run under it.
     */
    public function test_the_lock_is_keyed_on_the_module(): void
    {
        $this->assertSame('happy-hour-module-7', $this->schedule->lockKey(7));
        $this->assertNotSame($this->schedule->lockKey(7), $this->schedule->lockKey(8));
    }

    public function test_a_daily_schedule_expands_to_one_window_per_day(): void
    {
        $happyHour = $this->makeHappyHour([
            'duration_type' => HappyHour::DURATION_DAILY,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->assertCount(3, $this->schedule->expand($happyHour));
    }

    public function test_a_weekly_schedule_keeps_only_the_named_days(): void
    {
        $happyHour = $this->makeHappyHour([
            'duration_type' => HappyHour::DURATION_WEEKLY,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(13)->toDateString(),
            'weekly_days' => [now()->format('l')],
        ]);

        $windows = $this->schedule->expand($happyHour);

        $this->assertCount(2, $windows, 'one matching weekday in each of two weeks');
    }

    /** A permanent weekly rule has no end, so there is no finite set of dates to write. */
    public function test_a_permanent_weekly_rule_expands_to_nothing(): void
    {
        $happyHour = $this->makeHappyHour([
            'duration_type' => HappyHour::DURATION_WEEKLY,
            'is_permanent' => 1,
            'weekly_days' => [now()->format('l')],
        ]);

        $this->assertSame([], $this->schedule->expand($happyHour));
    }

    /** Custom dates each carry their own window -- the whole reason the type exists. */
    public function test_a_custom_schedule_keeps_per_date_times(): void
    {
        $first = now()->addDay()->toDateString();
        $second = now()->addDays(2)->toDateString();

        $happyHour = $this->makeHappyHour([
            'duration_type' => HappyHour::DURATION_CUSTOM,
            'custom_days' => [$first, $second],
            'custom_times' => ['14:00', '09:30'],
            'start_time' => null,
            'end_time' => null,
        ]);

        $windows = $this->schedule->expand($happyHour);

        $this->assertCount(2, $windows);
        $this->assertSame([$first, '14:00:00', '15:00:00'], array_values($windows[0]));
        $this->assertSame([$second, '09:30:00', '10:30:00'], array_values($windows[1]));
    }

    /**
     * The two lists are parallel, and a date whose time went missing has no window to run in.
     * Dropping it keeps the rest of the schedule saveable; expanding it with a null time is what
     * used to reach the overlap check and fail the whole save with a TypeError.
     */
    public function test_a_custom_date_with_no_time_is_dropped(): void
    {
        $happyHour = $this->makeHappyHour([
            'duration_type' => HappyHour::DURATION_CUSTOM,
            'custom_days' => [now()->addDay()->toDateString(), now()->addDays(2)->toDateString()],
            'custom_times' => ['14:00'],
            'start_time' => null,
            'end_time' => null,
        ]);

        $this->assertCount(1, $this->schedule->expand($happyHour));
    }

    public function test_materialising_replaces_previous_rows(): void
    {
        $happyHour = $this->makeHappyHour([
            'duration_type' => HappyHour::DURATION_DAILY,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->assertSame(3, $this->schedule->materialise($happyHour));
        $this->assertSame(3, HappyHourDate::where('happy_hour_id', $happyHour->id)->count());

        $happyHour->update(['end_date' => now()->toDateString()]);

        $this->assertSame(1, $this->schedule->materialise($happyHour->fresh()));
        $this->assertSame(1, HappyHourDate::where('happy_hour_id', $happyHour->id)->count());
    }

    public function test_an_overlapping_window_in_the_same_module_is_refused(): void
    {
        $happyHour = $this->makeHappyHour(['start_time' => '10:00:00', 'end_time' => '12:00:00']);
        $this->schedule->materialise($happyHour);

        $conflict = $this->schedule->firstConflict(
            $happyHour->module_id,
            [['date' => now()->toDateString(), 'start_time' => '11:00:00', 'end_time' => '13:00:00']]
        );

        $this->assertNotNull($conflict, 'an overlapping window must be refused');
        $this->assertSame($happyHour->id, $conflict['happy_hour_id']);
    }

    /** Adjacent windows are not overlapping -- refusing them would make back-to-back impossible. */
    public function test_a_window_that_starts_when_another_ends_is_allowed(): void
    {
        $happyHour = $this->makeHappyHour(['start_time' => '10:00:00', 'end_time' => '11:00:00']);
        $this->schedule->materialise($happyHour);

        $this->assertNull($this->schedule->firstConflict(
            $happyHour->module_id,
            [['date' => now()->toDateString(), 'start_time' => '11:00:00', 'end_time' => '12:00:00']]
        ));
    }

    /** The same window in another module is not a conflict -- that is what D2 buys. */
    public function test_the_same_window_in_another_module_is_not_a_conflict(): void
    {
        $happyHour = $this->makeHappyHour(['start_time' => '10:00:00', 'end_time' => '12:00:00']);
        $this->schedule->materialise($happyHour);

        $otherModuleId = DB::table('modules')->where('id', '!=', $happyHour->module_id)->value('id');

        if (! $otherModuleId) {
            $this->markTestSkipped('needs two modules');
        }

        $this->assertNull($this->schedule->firstConflict(
            $otherModuleId,
            [['date' => now()->toDateString(), 'start_time' => '10:00:00', 'end_time' => '12:00:00']]
        ));
    }

    /** Editing a happy hour must not collide with its own rows. */
    public function test_a_happy_hour_does_not_conflict_with_itself(): void
    {
        $happyHour = $this->makeHappyHour(['start_time' => '10:00:00', 'end_time' => '12:00:00']);
        $this->schedule->materialise($happyHour);

        $this->assertNull($this->schedule->firstConflict(
            $happyHour->module_id,
            [['date' => now()->toDateString(), 'start_time' => '10:00:00', 'end_time' => '12:00:00']],
            $happyHour->id
        ));
    }

    public function test_the_lock_is_released_after_use(): void
    {
        $store = Store::first();

        if (! $store || ! $store->module_id) {
            $this->markTestSkipped('dataset has no store in a module');
        }

        $first = $this->schedule->withLock($store->module_id, fn () => 'done');
        $second = $this->schedule->withLock($store->module_id, fn () => 'again');

        $this->assertSame('done', $first);
        $this->assertSame('again', $second, 'a released lock must be takeable again');
    }

    /**
     * A permanent weekly rule writes no dated rows, so a dated-row comparison cannot see it in
     * either direction -- and two permanent Monday 10-11 rules in one module both saved, which
     * breaks the "exactly one happy hour runs here" singular the catalog depends on.
     */
    public function test_two_permanent_weekly_rules_cannot_cover_the_same_day(): void
    {
        $first = $this->makeHappyHour([
            'duration_type' => HappyHour::DURATION_WEEKLY,
            'is_permanent' => 1,
            'weekly_days' => ['Monday'],
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);

        $second = $this->makeHappyHour([
            'duration_type' => HappyHour::DURATION_WEEKLY,
            'is_permanent' => 1,
            'weekly_days' => ['Monday'],
            'start_time' => '10:30:00',
            'end_time' => '11:30:00',
        ]);

        $this->assertNotNull(
            $this->schedule->permanentConflict($second, $second->id),
            'a second permanent rule on the same weekday and window must be refused'
        );

        // A different weekday is not a clash.
        $second->update(['weekly_days' => ['Tuesday']]);

        $this->assertNull($this->schedule->permanentConflict($second->fresh(), $second->id));

        $this->assertNotNull($first->id);
    }

    /** And a dated schedule cannot slip under a permanent rule that already covers its weekday. */
    public function test_a_dated_window_is_blocked_by_a_permanent_weekly_rule(): void
    {
        $permanent = $this->makeHappyHour([
            'duration_type' => HappyHour::DURATION_WEEKLY,
            'is_permanent' => 1,
            'weekly_days' => ['Monday'],
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);

        $monday = now()->next('Monday')->toDateString();

        $this->assertTrue($this->schedule->blockedByPermanentWeekly(
            $permanent->module_id, $monday, '10:30:00', '11:30:00'
        ));

        // Outside its hours, and on another weekday, are both fine.
        $this->assertFalse($this->schedule->blockedByPermanentWeekly(
            $permanent->module_id, $monday, '15:00:00', '16:00:00'
        ));

        $this->assertFalse($this->schedule->blockedByPermanentWeekly(
            $permanent->module_id, now()->next('Tuesday')->toDateString(), '10:30:00', '11:30:00'
        ));
    }

    private function makeHappyHour(array $overrides = []): HappyHour
    {
        $store = Store::first();

        if (! $store || ! $store->module_id) {
            $this->markTestSkipped('dataset has no store with a module');
        }

        return HappyHour::create(array_merge([
            'module_id' => $store->module_id,
            'title' => 'schedule probe',
            'discount' => 10,
            'duration_type' => HappyHour::DURATION_DAILY,
            'is_permanent' => 0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'status' => 1,
        ], $overrides));
    }
}
