<?php

namespace App\Services\Promotion;

use App\Models\HappyHour;
use App\Models\HappyHourStore;
use App\Services\BaseService;
use App\Support\Storage\FileStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * The admin's side of Happy Hour: publishing windows and deciding who runs them.
 *
 * Saving is the interesting part. A happy hour must not overlap another in the same module, and
 * the check plus the write have to happen under one lock -- without it two saves check against
 * each other's uncommitted rows and both succeed, which is how a double-click produced two
 * identical windows.
 */
class HappyHourAdminService extends BaseService
{
    public function __construct(private HappyHourScheduleService $schedule) {}

    public function list(?string $search, ?int $moduleId, int $perPage): LengthAwarePaginator
    {
        return HappyHour::withCount([
            // A denied request is not a store on the happy hour: counting it made the column read
            // higher than the number of stores that could ever apply the discount.
            'enrollments as enrollments_count' => fn ($q) => $q->whereIn('status', [
                HappyHourStore::STATUS_APPROVED, HappyHourStore::STATUS_PENDING,
            ]),
            'enrollments as approved_count' => fn ($q) => $q->where('status', HappyHourStore::STATUS_APPROVED),
        ])
            ->withMax('dates as last_applicable_date', 'applicable_date')
            // Today's rows only, and only so isRunningNow() can answer from memory: without them
            // it falls back to its own exists() query and the listing paid one per row. The full
            // expansion is never needed here -- the column beside it reads last_applicable_date,
            // which is the aggregate above, not this relation.
            ->with([
                'module',
                'dates' => fn ($q) => $q->whereDate('applicable_date', now()->toDateString()),
            ])
            ->when($moduleId, fn ($q) => $q->where('module_id', $moduleId))
            ->when($search, function ($query) use ($search) {
                // Trimmed and emptied out first: a padded search splits into empty pieces, each of
                // which becomes LIKE '%%' and matches every row -- so "  term  " answered with the
                // whole list instead of the one match. Grouped so the OR chain cannot escape the
                // module filter beside it.
                $query->where(function ($q) use ($search) {
                    foreach (array_filter(explode(' ', trim($search))) as $word) {
                        $q->orWhere('title', 'like', "%{$word}%");
                    }
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Create a happy hour.
     *
     * Two ways it does not return a model, and the caller has to tell them apart:
     *   HappyHourConflict thrown -- the schedule overlaps an existing one; the exception carries
     *                               the clashing date and window, which is what the inline alert
     *                               names so the admin knows where to go and fix it.
     *   null returned            -- the lock could not be taken, i.e. someone else is mid-save
     *                               for this module. Also a 409, but a retryable one.
     */
    public function store(Request $request): ?HappyHour
    {
        // The panel's current module, not a posted field: every admin screen is scoped by the
        // header's module switcher, so a happy hour belongs to whichever module the admin was in.
        // It is the whole of the scope -- there is no zone half.
        $moduleId = (int) Config::get('module.current_module_id');

        return $this->schedule->withLock($moduleId, function () use ($request, $moduleId) {
            return DB::transaction(function () use ($request, $moduleId) {
                $happyHour = new HappyHour($this->attributes($request));
                $happyHour->module_id = $moduleId;
                $happyHour->admin_id = auth('admin')->id();

                if ($request->hasFile('cover_image')) {
                    $happyHour->cover_image = FileStorage::upload('happy_hour/', $request->file('cover_image'), 2, 'jpg,jpeg,png');
                }

                if ($request->hasFile('icon')) {
                    $happyHour->icon = FileStorage::upload('happy_hour/', $request->file('icon'), 2, 'jpg,jpeg,png,gif');
                }

                // Expanded before saving so a clash is found before anything is written -- the
                // transaction would roll back either way, but this keeps the conflict message
                // about the schedule rather than about a failed insert.
                $happyHour->save();

                if ($conflict = $this->conflictFor($happyHour)) {
                    throw new HappyHourConflict($conflict);
                }

                $this->schedule->materialise($happyHour);
                return $happyHour;
            });
        }) ?? null;
    }

    /** Same two failure modes as store(); see its docblock. */
    public function update(HappyHour $happyHour, Request $request): ?HappyHour
    {
        return $this->schedule->withLock($happyHour->module_id, function () use ($happyHour, $request) {
            return DB::transaction(function () use ($happyHour, $request) {
                $happyHour->fill($this->attributes($request));

                if ($request->hasFile('cover_image')) {
                    $happyHour->cover_image = FileStorage::update('happy_hour/', $happyHour->cover_image, $request->file('cover_image'), 2, 'jpg,jpeg,png');
                }

                if ($request->hasFile('icon')) {
                    $happyHour->icon = FileStorage::update('happy_hour/', $happyHour->icon, $request->file('icon'), 2, 'jpg,jpeg,png,gif');
                }

                $happyHour->save();

                // Ignores its own rows, so re-saving an unchanged schedule is not a conflict
                // with itself.
                if ($conflict = $this->conflictFor($happyHour, $happyHour->id)) {
                    throw new HappyHourConflict($conflict);
                }

                $this->schedule->materialise($happyHour);
                return $happyHour;
            });
        }) ?? null;
    }

    /**
     * The first window of this schedule that collides with an existing one, or null.
     *
     * Two comparisons, because a permanent weekly rule writes no dated rows and so is invisible
     * to a dated-row comparison in either direction: a permanent rule is compared against other
     * permanent rules and against future dated rows, and a finite schedule is additionally asked
     * whether a permanent rule already covers each of its days.
     */
    public function conflictFor(HappyHour $happyHour, ?int $ignoreId = null): ?array
    {
        $windows = $this->schedule->expand($happyHour);

        if (! $windows) {
            return $this->schedule->permanentConflict($happyHour, $ignoreId);
        }

        foreach ($windows as $window) {
            if ($this->schedule->blockedByPermanentWeekly(
                $happyHour->module_id, $window['date'], $window['start_time'], $window['end_time'], $ignoreId
            )) {
                return [
                    'date' => $window['date'],
                    'message' => translate('messages.A permanent happy hour already covers').' '
                        .$window['date'].' '.translate('messages.in this module'),
                ];
            }
        }

        return $this->schedule->firstConflict(
            $happyHour->module_id,
            $windows,
            $ignoreId
        );
    }

    public function setStatus(HappyHour $happyHour, int $status): void
    {
        $happyHour->update(['status' => $status]);
    }

    public function delete(HappyHour $happyHour): void
    {
        DB::transaction(function () use ($happyHour) {
            $happyHour->translations()->delete();
            // Dates and enrolments cascade on the foreign key.
            $happyHour->delete();
        });
    }

    /**
     * Write an admin decision, but only while the request is still pending.
     *
     * Two admins can open the same request and answer it in the same second. A read, a status
     * check and a save is three steps, so both pass the check and both write: the row ends up
     * right, but the loser is told their answer was recorded when the winner's stands, and the
     * store is notified twice. The condition rides on the UPDATE instead, so exactly one of them
     * touches a row and the other gets nothing back.
     */
    public function applyPendingDecision(HappyHourStore $enrollment, array $attributes): bool
    {
        return HappyHourStore::whereKey($enrollment->id)
            ->where('status', HappyHourStore::STATUS_PENDING)
            ->update($attributes + ['checked' => 0]) > 0;
    }

    /**
     * The form's shape, turned into the row's.
     *
     * They are not the same shape and the gap is deliberate on the form's side: the schedule
     * builder is one widget with three modes, so it posts flat hidden fields -- a "start - end"
     * date_range string, comma-joined day and time lists -- rather than three sets of columns.
     * Every mode nulls the columns the other two own, or a happy hour switched from weekly to
     * custom would keep its old weekday rule and match on both.
     */
    private function attributes(Request $request): array
    {
        $durationType = $request->input('duration_type', HappyHour::DURATION_DAILY);

        // Permanent belongs to weekly alone: it means "this weekday rule has no end", which a
        // dated schedule cannot express.
        $isPermanent = $durationType === HappyHour::DURATION_WEEKLY && $request->boolean('is_permanent');

        $attributes = [
            'title' => $this->defaultLangValue($request, 'title'),
            'short_description' => $this->defaultLangValue($request, 'short_description'),
            'discount' => (float) $request->input('discount'),
            // Optional, behind a toggle. The checkbox itself posts nothing when off, so
            // min_order_enabled carries that state -- without it a happy hour saved with the
            // minimum switched on and empty.
            'min_order_amount' => $request->boolean('min_order_enabled')
                ? (float) $request->input('min_order_amount')
                : null,
            'duration_type' => $durationType,
            'is_permanent' => $isPermanent,
            'status' => 1,
        ];

        if ($durationType === HappyHour::DURATION_CUSTOM) {
            // Custom names its own dates and times, one pair per entry, so it owns neither a
            // weekday rule nor a range nor a single daily window.
            return $attributes + [
                'custom_days' => array_values(array_filter(explode(',', (string) $request->input('custom_days')))),
                'custom_times' => array_values(array_filter(explode(',', (string) $request->input('custom_times')))),
                'weekly_days' => null,
                'start_date' => null,
                'end_date' => null,
                'start_time' => null,
                'end_time' => null,
            ];
        }

        $startTime = $this->normaliseTime($request->input('start_time'));

        $attributes += [
            'custom_days' => null,
            'custom_times' => null,
            'weekly_days' => $durationType === HappyHour::DURATION_WEEKLY
                ? array_values(array_filter(explode(',', (string) $request->input('weekly_days'))))
                : null,
            'start_time' => $startTime,
            // The window is a fixed length rather than a second field: the admin picks when it
            // starts and the platform decides how long it runs, which is what makes two windows
            // comparable for the overlap check.
            'end_time' => $this->deriveEndTime($startTime),
        ];

        if ($isPermanent) {
            return $attributes + ['start_date' => null, 'end_date' => null];
        }

        // "2026-09-02 - 2026-09-30", as the range picker joins it. A one-day pick posts only the
        // start, so the end falls back to it rather than to null -- a null end reads as permanent
        // everywhere else.
        [$start, $end] = array_pad(explode(' - ', (string) $request->input('date_range')), 2, null);

        return $attributes + [
            'start_date' => $start ? date('Y-m-d', strtotime($start)) : null,
            'end_date' => date('Y-m-d', strtotime($end ?: $start ?: 'today')),
        ];
    }

    private function normaliseTime(?string $time): ?string
    {
        return $time ? date('H:i:s', strtotime($time)) : null;
    }

    private function deriveEndTime(?string $startTime): ?string
    {
        return $startTime
            ? date('H:i:s', strtotime($startTime) + HappyHour::DURATION_MINUTES * 60)
            : null;
    }

    /**
     * The value posted for the default language.
     *
     * title[] and lang[] are parallel arrays -- the house convention. The default entry is found
     * by locating 'default' in lang[] and reading the same index.
     */
    private function defaultLangValue(Request $request, string $key): ?string
    {
        $values = $request->input($key);

        if (! is_array($values)) {
            return $values;
        }

        $langs = (array) $request->input('lang', []);
        $index = array_search('default', $langs, true);

        return $index !== false ? ($values[$index] ?? null) : ($values[0] ?? null);
    }
}
