<?php

namespace App\Services\Promotion;

use App\CentralLogics\Helpers;
use App\Models\HappyHour;
use App\Models\HappyHourDate;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Expanding a happy hour's schedule into dated rows, and refusing one that collides.
 *
 * Two happy hours in the same module may not overlap in time. The rule is enforced here
 * rather than in the schema because it depends on duration_type -- a daily window has to be
 * expanded before it can be compared to a custom one -- and because the answer changes as the
 * calendar moves.
 */
class HappyHourScheduleService
{
    /** MySQL DAYOFWEEK numbering, where Sunday is 1. */
    private const WEEKDAY_NUMBERS = [
        'Sunday' => 1, 'Monday' => 2, 'Tuesday' => 3, 'Wednesday' => 4,
        'Thursday' => 5, 'Friday' => 6, 'Saturday' => 7,
    ];

    /**
     * Keyed on the module alone, which is the whole of a happy hour's scope.
     *
     * Narrow on purpose: a broader key would serialise saves that cannot possibly conflict, and a
     * key narrower than the overlap check itself would not protect it -- the lock has to cover
     * exactly the set the check reads.
     */
    public function lockKey(int $moduleId): string
    {
        return 'happy-hour-module-'.$moduleId;
    }

    /**
     * Runs $callback while holding the module lock, or returns null if it cannot be taken.
     *
     * A null return is a 409 to the caller, not an error: someone else is mid-save for the same
     * module, and the right answer is to say so rather than to queue behind them.
     */
    public function withLock(int $moduleId, callable $callback, int $seconds = 10, int $waitFor = 5)
    {
        $lock = Cache::lock($this->lockKey($moduleId), $seconds);

        // block() without a callback returns true or throws once $waitFor elapses -- passing a
        // callback would run it under the lock and return ITS value, which cannot be told apart
        // from "could not acquire".
        try {
            $lock->block($waitFor);
        } catch (LockTimeoutException) {
            return null;
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * The first date and time an existing happy hour collides with the given windows, or null.
     *
     * Compared against the materialised rows rather than the definitions, because that is the
     * only shape all three duration types share -- comparing a weekly rule to a custom date list
     * in their own terms would need a case per pairing.
     *
     * Windows touching end-to-start do not overlap: a 10:00-11:00 and an 11:00-12:00 are
     * adjacent, and refusing that would make back-to-back windows impossible.
     */
    public function firstConflict(int $moduleId, array $windows, ?int $ignoreHappyHourId = null): ?array
    {
        foreach ($windows as $window) {
            $conflict = HappyHourDate::where('module_id', $moduleId)
                ->whereDate('applicable_date', $window['date'])
                ->when($ignoreHappyHourId, fn ($q) => $q->where('happy_hour_id', '!=', $ignoreHappyHourId))
                ->whereTime('start_time', '<', $window['end_time'])
                ->whereTime('end_time', '>', $window['start_time'])
                ->first();

            if ($conflict) {
                return [
                    'date' => $window['date'],
                    'start_time' => $conflict->start_time,
                    'end_time' => $conflict->end_time,
                    'happy_hour_id' => $conflict->happy_hour_id,
                    'message' => translate('messages.Time conflict on').' '.$window['date'].' '
                        .translate('messages.in this module'),
                ];
            }
        }

        return null;
    }

    /**
     * The overlap a permanent weekly rule is in, or null.
     *
     * A permanent rule expands to no dated rows, so firstConflict() -- which compares dated rows
     * -- cannot see it. Both directions need their own comparison, or two permanent Monday 10-11
     * rules in one module both save and runningIn() stops being the singular it is documented as.
     *
     * @return array{date: string|null, message: string}|null
     */
    public function permanentConflict(HappyHour $happyHour, ?int $ignoreHappyHourId = null): ?array
    {
        $days = (array) ($happyHour->weekly_days ?: []);

        if (! $days || ! $happyHour->start_time || ! $happyHour->end_time) {
            return null;
        }

        // Against another permanent rule: definition to definition, since neither has dated rows.
        $clash = HappyHour::where('module_id', $happyHour->module_id)
            ->where('duration_type', HappyHour::DURATION_WEEKLY)
            ->where('is_permanent', 1)
            ->when($ignoreHappyHourId, fn ($q) => $q->where('id', '!=', $ignoreHappyHourId))
            ->where('start_time', '<', $happyHour->end_time)
            ->where('end_time', '>', $happyHour->start_time)
            ->get()
            ->first(fn (HappyHour $other) => array_intersect($days, (array) ($other->weekly_days ?: [])));

        if ($clash) {
            return [
                'date' => null,
                'message' => translate('messages.A permanent happy hour already covers these days in this module'),
            ];
        }

        // And against dated rows a finite schedule already wrote. DAYOFWEEK rather than DAYNAME:
        // DAYNAME follows lc_time_names and stops matching the English weekday names stored here
        // the moment that server variable differs.
        $numbers = array_values(array_filter(array_map(
            fn ($day) => self::WEEKDAY_NUMBERS[$day] ?? null,
            $days
        )));

        if (! $numbers) {
            return null;
        }

        $row = HappyHourDate::where('module_id', $happyHour->module_id)
            ->when($ignoreHappyHourId, fn ($q) => $q->where('happy_hour_id', '!=', $ignoreHappyHourId))
            // A past occurrence cannot clash with a rule that starts applying now.
            ->whereDate('applicable_date', '>=', now()->toDateString())
            ->whereRaw('DAYOFWEEK(applicable_date) IN ('.implode(',', $numbers).')')
            ->whereTime('start_time', '<', $happyHour->end_time)
            ->whereTime('end_time', '>', $happyHour->start_time)
            ->orderBy('applicable_date')
            ->first();

        return $row ? [
            'date' => (string) $row->applicable_date,
            'message' => translate('messages.Time conflict on').' '.$row->applicable_date.' '
                .translate('messages.in this module'),
        ] : null;
    }

    /**
     * Whether this happy hour's schedule collides with any of the given ones, or null.
     *
     * A backstop rather than a second implementation of the admin rule. The admin side already
     * forbids two overlapping happy hours in one module, and a store belongs to one module, so in
     * new data this can only fire on rows written before that rule covered permanent weekly
     * schedules. It is kept because the cost of being wrong is a store running two discounts at
     * once, which the pricing side resolves silently by taking whichever it finds first.
     *
     * @param  int[]  $happyHourIds
     * @return array{title: string, window: string}|null
     */
    public function overlapWith(HappyHour $candidate, array $happyHourIds): ?array
    {
        if (! $happyHourIds) {
            return null;
        }

        $others = HappyHour::whereIn('id', $happyHourIds)->get();
        $candidateWindows = $this->expand($candidate);
        $candidateDays = (array) ($candidate->weekly_days ?: []);

        foreach ($others as $other) {
            if (! $this->timesOverlap($candidate, $other)) {
                continue;
            }

            $otherWindows = $this->expand($other);

            // Two permanent rules share no dates to compare, so they are matched on weekday.
            $clashes = (! $candidateWindows && ! $otherWindows)
                ? (bool) array_intersect($candidateDays, (array) ($other->weekly_days ?: []))
                // One permanent, one dated: does the dated side ever fall on a covered weekday?
                : ($candidateWindows && $otherWindows
                    ? (bool) array_intersect(array_column($candidateWindows, 'date'), array_column($otherWindows, 'date'))
                    : $this->datedFallsOnWeekdays(
                        $candidateWindows ?: $otherWindows,
                        $candidateWindows ? (array) ($other->weekly_days ?: []) : $candidateDays
                    ));

            if ($clashes) {
                return [
                    'title' => $other->title,
                    'window' => Helpers::time_format($other->start_time).' - '.Helpers::time_format($other->end_time),
                ];
            }
        }

        return null;
    }

    /** Times overlap when each starts before the other ends. Touching end-to-start does not count. */
    private function timesOverlap(HappyHour $a, HappyHour $b): bool
    {
        return $a->start_time && $a->end_time && $b->start_time && $b->end_time
            && $a->start_time < $b->end_time
            && $a->end_time > $b->start_time;
    }

    /** @param  array<int, array{date: string}>  $windows */
    private function datedFallsOnWeekdays(array $windows, array $weekdays): bool
    {
        if (! $weekdays) {
            return false;
        }

        foreach ($windows as $window) {
            if (in_array(Carbon::parse($window['date'])->format('l'), $weekdays, true)) {
                return true;
            }
        }

        return false;
    }

    /** Whether a dated window falls under a permanent weekly rule already covering that weekday. */
    public function blockedByPermanentWeekly(int $moduleId, string $date, string $startTime, string $endTime, ?int $ignoreHappyHourId = null): bool
    {
        return HappyHour::where('module_id', $moduleId)
            ->where('duration_type', HappyHour::DURATION_WEEKLY)
            ->where('is_permanent', 1)
            ->whereJsonContains('weekly_days', Carbon::parse($date)->format('l'))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->when($ignoreHappyHourId, fn ($q) => $q->where('id', '!=', $ignoreHappyHourId))
            ->exists();
    }

    /**
     * Every window a happy hour's schedule resolves to, as [date, start_time, end_time].
     *
     * A permanent weekly rule expands to nothing: it has no end, so there is no finite set of
     * dates to write, and isRunningNow() matches it against its definition instead. Its overlap
     * is checked by permanentConflict() rather than here.
     */
    public function expand(HappyHour $happyHour): array
    {
        if ($happyHour->is_permanent && $happyHour->duration_type === HappyHour::DURATION_WEEKLY) {
            return [];
        }

        return match ($happyHour->duration_type) {
            HappyHour::DURATION_CUSTOM => $this->expandCustom($happyHour),
            HappyHour::DURATION_WEEKLY => $this->expandWeekly($happyHour),
            default => $this->expandDaily($happyHour),
        };
    }

    /** Every day between the two dates, at the same time each day. */
    private function expandDaily(HappyHour $happyHour): array
    {
        $windows = [];
        $cursor = Carbon::parse($happyHour->start_date);
        $end = Carbon::parse($happyHour->end_date ?? $happyHour->start_date);

        while ($cursor->lessThanOrEqualTo($end)) {
            $windows[] = [
                'date' => $cursor->toDateString(),
                'start_time' => $happyHour->start_time,
                'end_time' => $happyHour->end_time,
            ];

            $cursor->addDay();
        }

        return $windows;
    }

    /** The same, restricted to the named weekdays. */
    private function expandWeekly(HappyHour $happyHour): array
    {
        $days = array_map('strtolower', (array) ($happyHour->weekly_days ?? []));

        return array_values(array_filter(
            $this->expandDaily($happyHour),
            fn ($window) => in_array(strtolower(Carbon::parse($window['date'])->format('l')), $days, true)
        ));
    }

    private function expandCustom(HappyHour $happyHour): array
    {
        $times = array_values((array) ($happyHour->custom_times ?? []));
        $windows = [];

        foreach (array_values((array) ($happyHour->custom_days ?? [])) as $index => $date) {
            $startTime = $this->normaliseTime($times[$index] ?? $happyHour->start_time);

            if (! $startTime) {
                continue;
            }

            $windows[] = [
                'date' => Carbon::parse($date)->toDateString(),
                'start_time' => $startTime,
                'end_time' => $this->deriveEndTime($startTime),
            ];
        }

        return $windows;
    }

    private function normaliseTime(?string $time): ?string
    {
        return $time ? date('H:i:s', strtotime($time)) : null;
    }

    private function deriveEndTime(string $startTime): string
    {
        return date('H:i:s', strtotime($startTime) + HappyHour::DURATION_MINUTES * 60);
    }

    /**
     * Replace a happy hour's materialised rows with the ones its schedule now resolves to.
     *
     * Delete-then-write rather than a diff: the schedule is small, and reconciling it would have
     * to decide what a changed time on an existing date means. Runs inside the caller's
     * transaction so a failed write cannot leave a half-expanded schedule behind.
     */
    public function materialise(HappyHour $happyHour): int
    {
        HappyHourDate::where('happy_hour_id', $happyHour->id)->delete();

        $windows = $this->expand($happyHour);

        foreach ($windows as $window) {
            HappyHourDate::create([
                'happy_hour_id' => $happyHour->id,
                'module_id' => $happyHour->module_id,
                'applicable_date' => $window['date'],
                'start_time' => $window['start_time'],
                'end_time' => $window['end_time'],
                'status' => 1,
            ]);
        }

        return count($windows);
    }
}
