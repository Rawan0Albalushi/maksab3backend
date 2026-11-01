<?php

namespace App\Services\Zone;

use App\Models\SurgePrice;
use App\Services\BaseService;
use App\Services\System\ModuleService;
use App\Services\Order\DeliveryChargeService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Owns App\Models\SurgePrice — port doc §9.
 *
 * Step 6 of the fee pipeline: a temporary increase an admin schedules for a (zone, module). The
 * ARITHMETIC is not here — `DeliveryChargeService::surgeAmount()` is the one place a fee is
 * increased (N1), and `applyToFee()` below calls it rather than repeating it. What lives here is
 * the QUESTION "is anything surging this zone and module at this moment", which the resolver
 * answers and memoises.
 *
 * Two ways a surge can apply, in this order:
 *
 *  1. A row in `surge_price_dates` — the expanded calendar the admin screen writes when a daily,
 *     dated-weekly or custom surge is saved. Keyed on (zone, module, date) with a time window.
 *  2. A PERMANENT weekly surge — no dates are expanded for it because it never ends, so it is
 *     matched live against the weekday and the time.
 */
class SurgePriceService extends BaseService
{
    /**
     * The modules this surge is the only active one for in its zone.
     *
     * The JSON-column counterpart of ResolvesSoloModuleCoverTrait: surge price keeps its module
     * ids in a cast `module_ids` array rather than a pivot, so the "who else covers it" question
     * is answered in PHP over the zone's other active surges instead of with an `exists()` each.
     * One query either way.
     *
     * A surge is an ADD-ON. A module losing it is still available in the zone — it just stops
     * being surcharged — and the warning says exactly that, no more.
     *
     * @return array<int, string> module id => module name
     */
    public function soloModules(SurgePrice $surge): array
    {
        if (! $surge->status) {
            return [];
        }

        $coveredElsewhere = SurgePrice::query()
            ->where('zone_id', $surge->zone_id)
            ->where('status', 1)
            ->whereKeyNot($surge->getKey())
            ->pluck('module_ids')
            ->flatMap(fn ($ids) => array_map('intval', (array) $ids))
            ->unique()
            ->all();

        $names = app(ModuleService::class)->getSelectOptions()->pluck('module_name', 'id');

        $solo = [];

        foreach (array_map('intval', (array) ($surge->module_ids ?? [])) as $moduleId) {
            if (in_array($moduleId, $coveredElsewhere, true) || ! $names->has($moduleId)) {
                continue;
            }

            $solo[$moduleId] = (string) $names[$moduleId];
        }

        return $solo;
    }

    /**
     * Resolved surges, keyed "zone:module:minute".
     *
     * Static so a fresh resolution cannot lose it. A store listing asks the same question once
     * per card otherwise (M8), and a quote asks it again for the same order. The minute is part
     * of the key because a surge starts and stops on a clock time — a longer bucket would carry
     * a stale answer across the boundary.
     */
    private static array $memo = [];

    /** For tests that move the clock, and for any write path that changes what is scheduled. */
    public function flushMemo(): void
    {
        self::$memo = [];
    }

    /**
     * What is surging this (zone, module) at this moment, or an empty surge.
     *
     * §9.2 — a SCHEDULED order is surged for the slot it will be delivered in, not for the
     * moment the customer checks out, so callers pass `schedule_at` rather than letting this
     * default to now.
     *
     * @return array{title:string,customer_note:string,customer_note_status:int,price:float|string,price_type:string}
     */
    public function resolve(mixed $zoneId, mixed $moduleId, mixed $datetime = null): array
    {
        if (! $zoneId || ! $moduleId) {
            return $this->empty();
        }

        $when = $datetime instanceof Carbon ? $datetime->copy() : Carbon::parse($datetime ?: 'now');
        $key = $zoneId.':'.$moduleId.':'.$when->format('Y-m-d H:i');

        return self::$memo[$key] ??= $this->lookup($zoneId, $moduleId, $when);
    }

    /**
     * The fee with the surge applied.
     *
     * Delegates to the engine so the increase is computed in exactly one place (N1). A second
     * implementation here is how a checkout ends up quoting one price and charging another.
     */
    public function applyToFee(float $fee, array $surge): float
    {
        return $fee + app(DeliveryChargeService::class)->surgeAmount($fee, $surge);
    }

    /**
     * The note to show beside the fee, or null.
     *
     * §9.3 — shown only when the admin switched it on, and only when something is actually
     * being surged. Pass the charge and a zero one returns null: nothing is surged onto a free
     * delivery, so there is nothing to explain.
     */
    public function customerNote(array $surge, ?float $charge = null): ?string
    {
        if ((int) ($surge['customer_note_status'] ?? 0) !== 1) {
            return null;
        }

        if ((float) ($surge['price'] ?? 0) <= 0 || ($charge !== null && $charge <= 0)) {
            return null;
        }

        $note = trim((string) ($surge['customer_note'] ?? ''));

        return $note === '' ? null : $note;
    }

    /** No surge. The same keys as a real one, so nothing downstream has to test for absence. */
    public function empty(): array
    {
        return [
            'title' => '',
            'customer_note' => '',
            'customer_note_status' => 0,
            'price' => 0,
            'price_type' => 'amount',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD — what the admin screen writes
    |--------------------------------------------------------------------------
    |
    | Saving a surge does two things: it writes the row, and it EXPANDS the schedule into
    | `surge_price_dates` — one row per (module, date) with a time window — which is what the
    | resolver's first branch reads. A permanent weekly surge is the exception: it never ends, so
    | there are no dates to expand and the resolver matches it live.
    */

    public function create(array $data): SurgePrice
    {
        return DB::transaction(function () use ($data) {
            $surge = new SurgePrice;
            $surge->forceFill($this->attributes($data) + ['zone_id' => $data['zone_id'], 'status' => $data['status'] ?? 1]);
            $surge->save();

            $this->writeWindows($surge);
            $this->flushMemo();

            return $surge;
        });
    }

    public function update(mixed $id, array $data): ?SurgePrice
    {
        return DB::transaction(function () use ($id, $data) {
            $surge = SurgePrice::find($id);

            if (! $surge) {
                return null;
            }

            $surge->forceFill($this->attributes($data));
            $surge->save();

            // The expansion is rebuilt rather than patched: a change of schedule type can move
            // every date, and a stale row would keep surging a day the admin removed.
            $surge->details()->delete();
            $this->writeWindows($surge);
            $this->flushMemo();

            return $surge;
        });
    }

    public function delete(mixed $id): bool
    {
        return DB::transaction(function () use ($id) {
            $surge = SurgePrice::find($id);

            if (! $surge) {
                return false;
            }

            $surge->details()->delete();
            $surge->translations()->delete();
            $this->flushMemo();

            return (bool) $surge->delete();
        });
    }

    public function updateStatus(mixed $id, mixed $status): bool
    {
        $surge = SurgePrice::find($id);
        $this->flushMemo();

        if (! $surge) {
            return false;
        }

        // forceFill, because SurgePrice declares no $fillable — the same reason create() and
        // update() do. A plain update() throws MassAssignmentException on this model.
        $surge->forceFill(['status' => (int) (bool) $status]);

        return (bool) $surge->save();
    }

    public function find(mixed $id, array $with = ['zone']): ?SurgePrice
    {
        return SurgePrice::with($with)->find($id);
    }

    /** Every locale, for the edit form's language tabs. */
    public function findForEdit(mixed $id): ?SurgePrice
    {
        return SurgePrice::withoutGlobalScopes(['translate'])->with(['translations', 'zone'])->find($id);
    }

    public function getList(
        array $filters = [],
        array $with = ['zone'],
        array $withCount = [],
        array $paginate = ['per_page' => 25, 'page' => 1]
    ): LengthAwarePaginator {
        return $this->buildQuery($filters, $with, $withCount)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getListData(array $filters = [], array $with = ['zone'], array $withCount = []): Collection
    {
        return $this->buildQuery($filters, $with, $withCount)->get();
    }

    /**
     * DESIGN RULE G1 — "A Surge Price can be created for the same Zone and Module only if the
     * selected duration does not overlap with an existing Surge Price."
     *
     * NOT the same rule as D1, F1 and E1. A zone may be surged at breakfast and again at dinner,
     * so several surges per (zone, module) are fine; what is refused is an overlapping DURATION.
     *
     * Touching windows count as overlapping, because the resolver's bounds are inclusive: at
     * exactly 10:00, both `08:00-10:00` and `10:00-12:00` match, and which one wins is whichever
     * the query happened to return first.
     *
     * @return string[] one sentence per clash, naming the surge and when it collides
     */
    /**
     * The picked modules a surge can never apply to, by name.
     *
     * A surge is added to the delivery charge by DeliveryChargeService; rental, ride-share and
     * service price their own trips and bookings and never reach it. Capability-driven, so a new
     * type inherits the answer from `config('module.<type>.surge')` rather than a name list here.
     *
     * @return array<int, string>
     */
    public function surgeIncapableModuleNames(array $moduleIds): array
    {
        if ($moduleIds === []) {
            return [];
        }

        $modules = app(ModuleService::class);
        $capable = $modules->surgeCapableModuleIds();
        $picked = array_map('intval', $moduleIds);

        return $modules->getSelectOptions()
            ->filter(fn ($module) => in_array((int) $module->id, $picked, true))
            ->reject(fn ($module) => in_array((int) $module->id, $capable, true))
            ->pluck('module_name')
            ->values()
            ->all();
    }


    public function conflictingWindows(array $data, mixed $exceptId = null): array
    {
        $clashes = [];

        foreach ($this->plannedWindows($data) as $window) {
            $clash = $window['date'] !== null
                ? $this->clashOnDate($data['zone_id'], $window, $exceptId)
                : $this->clashOnWeekday($data['zone_id'], $window, $exceptId);

            if (! $clash) {
                continue;
            }

            // One message per clashing SURGE, showing the first date it collides on. Keyed by
            // name because a month-long overlap would otherwise report the same surge thirty
            // times, once per day, and bury the one fact the admin needs.
            $clashes[$clash['name']] ??= $clash['message'];

            if (count($clashes) >= 3) {
                break;
            }
        }

        return array_values($clashes);
    }

    /*
    |--------------------------------------------------------------------------
    | Schedule expansion
    |--------------------------------------------------------------------------
    */

    /**
     * The windows a payload asks for, before anything is saved.
     *
     * A concrete `date` for daily, dated-weekly and custom schedules; a bare `weekday` for a
     * permanent weekly one, which has no end and so cannot be expanded.
     *
     * @return array<int, array{module_id:int,date:?string,weekday:?string,start:string,end:string}>
     */
    private function plannedWindows(array $data): array
    {
        $windows = [];
        $modules = array_map('intval', (array) ($data['module_ids'] ?? []));
        $type = $data['duration_type'] ?? 'daily';
        $start = $data['start_time'] ?? null;
        $end = $data['end_time'] ?? null;

        foreach ($modules as $moduleId) {
            if ($type === 'custom') {
                foreach ((array) ($data['custom_days'] ?? []) as $index => $day) {
                    $pair = (array) ($data['custom_times'] ?? []);
                    [$from, $to] = $this->splitRange($pair[$index] ?? '');

                    if ($from === null) {
                        continue;
                    }

                    $windows[] = [
                        'module_id' => $moduleId,
                        'date' => date('Y-m-d', strtotime($day)),
                        'weekday' => null,
                        'start' => $from,
                        'end' => $to,
                    ];
                }

                continue;
            }

            $weekdays = $type === 'weekly' ? (array) ($data['weekly_days'] ?? []) : null;

            if ($type === 'weekly' && ! empty($data['is_permanent'])) {
                foreach ($weekdays as $weekday) {
                    $windows[] = [
                        'module_id' => $moduleId,
                        'date' => null,
                        'weekday' => $weekday,
                        'start' => $start,
                        'end' => $end,
                    ];
                }

                continue;
            }

            if (empty($data['start_date']) || empty($data['end_date']) || ! $start || ! $end) {
                continue;
            }

            $cursor = Carbon::parse($data['start_date'])->startOfDay();
            $last = Carbon::parse($data['end_date'])->startOfDay();

            while ($cursor->lte($last)) {
                if ($weekdays === null || in_array($cursor->format('l'), $weekdays, true)) {
                    $windows[] = [
                        'module_id' => $moduleId,
                        'date' => $cursor->format('Y-m-d'),
                        'weekday' => $cursor->format('l'),
                        'start' => $start,
                        'end' => $end,
                    ];
                }

                $cursor->addDay();
            }
        }

        return $windows;
    }

    /** Expand a saved surge into `surge_price_dates`. A permanent weekly one has nothing to expand. */
    private function writeWindows(SurgePrice $surge): void
    {
        $rows = [];

        foreach ($this->plannedWindows($this->payloadOf($surge)) as $window) {
            if ($window['date'] === null) {
                continue;
            }

            $rows[] = [
                'surge_price_id' => $surge->id,
                'zone_id' => $surge->zone_id,
                'module_id' => $window['module_id'],
                'applicable_date' => $window['date'],
                'start_time' => $window['start'],
                'end_time' => $window['end'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('surge_price_dates')->insert($chunk);
        }
    }

    /** A saved row read back in the same shape `plannedWindows()` takes. */
    private function payloadOf(SurgePrice $surge): array
    {
        return [
            'zone_id' => $surge->zone_id,
            'module_ids' => $surge->module_ids ?? [],
            'duration_type' => $surge->duration_type,
            'is_permanent' => $surge->is_permanent,
            'start_date' => $surge->start_date,
            'end_date' => $surge->end_date,
            'start_time' => $surge->start_time,
            'end_time' => $surge->end_time,
            'weekly_days' => $surge->weekly_days ?? [],
            'custom_days' => $surge->custom_days ?? [],
            'custom_times' => $surge->custom_times ?? [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | G1 — the overlap tests
    |--------------------------------------------------------------------------
    */

    /** @return array{name:string,message:string}|null */
    private function clashOnDate(mixed $zoneId, array $window, mixed $exceptId): ?array
    {
        $existing = DB::table('surge_price_dates')
            ->join('surge_prices', 'surge_prices.id', '=', 'surge_price_dates.surge_price_id')
            ->where('surge_price_dates.zone_id', $zoneId)
            ->where('surge_price_dates.module_id', $window['module_id'])
            ->where('surge_price_dates.applicable_date', $window['date'])
            ->when($exceptId, fn ($q) => $q->where('surge_price_dates.surge_price_id', '!=', $exceptId))
            ->where('surge_price_dates.start_time', '<=', $window['end'])
            ->where('surge_price_dates.end_time', '>=', $window['start'])
            ->value('surge_prices.surge_price_name');

        if ($existing) {
            return $this->clash($existing, $window['date'], $window['start'], $window['end']);
        }

        return $this->permanentClash($zoneId, $window['module_id'], $window['weekday'], $window, $exceptId);
    }

    /** @return array{name:string,message:string}|null */
    private function clashOnWeekday(mixed $zoneId, array $window, mixed $exceptId): ?array
    {
        $permanent = $this->permanentClash($zoneId, $window['module_id'], $window['weekday'], $window, $exceptId);

        if ($permanent) {
            return $permanent;
        }

        // A permanent surge has no end, so it also collides with every already-expanded date
        // that falls on the same weekday inside its hours.
        $rows = DB::table('surge_price_dates')
            ->join('surge_prices', 'surge_prices.id', '=', 'surge_price_dates.surge_price_id')
            ->where('surge_price_dates.zone_id', $zoneId)
            ->where('surge_price_dates.module_id', $window['module_id'])
            ->when($exceptId, fn ($q) => $q->where('surge_price_dates.surge_price_id', '!=', $exceptId))
            ->where('surge_price_dates.start_time', '<=', $window['end'])
            ->where('surge_price_dates.end_time', '>=', $window['start'])
            ->get(['surge_price_dates.applicable_date', 'surge_prices.surge_price_name']);

        foreach ($rows as $row) {
            if (Carbon::parse($row->applicable_date)->format('l') === $window['weekday']) {
                return $this->clash($row->surge_price_name, $row->applicable_date, $window['start'], $window['end']);
            }
        }

        return null;
    }

    /** A permanent weekly surge covering the same weekday and hours. */
    /** @return array{name:string,message:string}|null */
    private function permanentClash(mixed $zoneId, int $moduleId, ?string $weekday, array $window, mixed $exceptId): ?array
    {
        if (! $weekday) {
            return null;
        }

        $surge = SurgePrice::query()
            ->where('zone_id', $zoneId)
            ->where('duration_type', 'weekly')
            ->where('is_permanent', 1)
            ->whereJsonContains('weekly_days', $weekday)
            ->when($exceptId, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->where('start_time', '<=', $window['end'])
            ->where('end_time', '>=', $window['start'])
            ->get()
            ->first(fn (SurgePrice $row) => $this->coversModule($row, $moduleId));

        if (! $surge) {
            return null;
        }

        return [
            'name' => $surge->surge_price_name,
            'message' => translate('messages.A surge price already covers').' '.$surge->surge_price_name
                .' — '.$this->weekdayLabel($weekday).' '.$this->clock($window['start']).' - '.$this->clock($window['end']),
        ];
    }

    /** @return array{name:string,message:string} */
    private function clash(string $name, string $date, string $start, string $end): array
    {
        return [
            'name' => $name,
            'message' => translate('messages.A surge price already covers').' '.$name
                .' — '.date('d M Y', strtotime($date)).' '.$this->clock($start).' - '.$this->clock($end),
        ];
    }

    private function clock(?string $time): string
    {
        return $time ? date('h:i A', strtotime($time)) : '';
    }

    /**
     * A stored weekday ("Sunday") as the panel's language renders it.
     *
     * Carbon's, not translate()'s. isPersistableTranslationKey() refuses to keep weekday names in
     * the language files -- they are date DATA, produced localised by the date library already --
     * so a translate() lookup finds nothing, writes the key back on every render, and returns the
     * humanised English name in every locale. Public because SurgePriceController labels the same
     * stored days on its own screens.
     */
    public function weekdayLabel(string $day): string
    {
        try {
            return \Carbon\Carbon::parse($day)->locale(app()->getLocale())->isoFormat('dddd');
        } catch (\Throwable) {
            // An unparseable value stored by hand: show it rather than lose the row.
            return $day;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Attributes and queries
    |--------------------------------------------------------------------------
    */

    /** The columns a payload writes. The schedule type decides which are set and which are nulled. */
    private function attributes(array $data): array
    {
        $type = $data['duration_type'] ?? 'daily';
        $permanent = $type === 'weekly' && ! empty($data['is_permanent']);

        return [
            'surge_price_name' => $data['name'] ?? null,
            'customer_note' => $data['customer_note'] ?? null,
            'customer_note_status' => (int) ! empty($data['customer_note_status']),
            'module_ids' => array_map('intval', (array) ($data['module_ids'] ?? [])),
            'price' => $data['price'] ?? 0,
            'price_type' => $data['price_type'] ?? 'percent',
            'duration_type' => $type,
            'is_permanent' => (int) $permanent,
            // Nulled rather than left behind: a stale weekday list on a daily surge would be read
            // back into the edit form and shown as a schedule the admin never chose.
            'weekly_days' => $type === 'weekly' ? array_values((array) ($data['weekly_days'] ?? [])) : null,
            'custom_days' => $type === 'custom' ? array_values((array) ($data['custom_days'] ?? [])) : null,
            'custom_times' => $type === 'custom' ? array_values((array) ($data['custom_times'] ?? [])) : null,
            'start_date' => $type === 'custom' || $permanent ? null : ($data['start_date'] ?? null),
            'end_date' => $type === 'custom' || $permanent ? null : ($data['end_date'] ?? null),
            'start_time' => $type === 'custom' ? null : ($data['start_time'] ?? null),
            'end_time' => $type === 'custom' ? null : ($data['end_time'] ?? null),
        ];
    }

    /** "09:00 - 17:00" as ['09:00:00', '17:00:00'], or nulls when it will not split. */
    private function splitRange(?string $range): array
    {
        $parts = preg_split('/\s*-\s*/', trim((string) $range), 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return [null, null];
        }

        return [date('H:i:s', strtotime($parts[0])), date('H:i:s', strtotime($parts[1]))];
    }

    private function buildQuery(array $filters, array $with, array $withCount): Builder
    {
        return SurgePrice::query()
            ->with($with)
            ->withCount($withCount)
            ->when(! empty($filters['zone_id']), fn ($q) => $q->where('zone_id', $filters['zone_id']))
            ->when(isset($filters['status']) && $filters['status'] !== '', fn ($q) => $q->where('status', (int) $filters['status']))
            ->when(! empty($filters['search']), fn ($q) => $q->where(function ($sub) use ($filters) {
                $sub->where('surge_price_name', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('zone', fn ($z) => $z->where('name', 'like', '%'.$filters['search'].'%'));
            }))
            ->orderBy('id', 'desc');
    }

    private function lookup(mixed $zoneId, mixed $moduleId, Carbon $when): array
    {
        $date = $when->format('Y-m-d');
        $time = $when->format('H:i:s');

        // 1. The expanded calendar.
        $scheduled = DB::table('surge_price_dates')
            ->where('zone_id', $zoneId)
            ->where('module_id', $moduleId)
            ->where('applicable_date', $date)
            ->where('start_time', '<=', $time)
            ->where('end_time', '>=', $time)
            ->first();

        if ($scheduled) {
            $surge = SurgePrice::active()->whereKey($scheduled->surge_price_id)->first();

            if ($surge) {
                return $this->payload($surge);
            }
        }

        // 2. A permanent weekly surge, which has no expanded dates to find.
        //
        // The module test is done in PHP rather than SQL. `module_ids` is json, and matching it
        // in the query is what broke the code this replaces — see coversModule(). Narrowing by
        // zone, weekday and time first leaves a handful of rows at most, so this costs nothing.
        $permanent = SurgePrice::active()
            ->where('zone_id', $zoneId)
            ->where('duration_type', 'weekly')
            ->where('is_permanent', 1)
            ->whereJsonContains('weekly_days', $when->format('l'))
            ->where('start_time', '<=', $time)
            ->where('end_time', '>=', $time)
            ->get()
            ->first(fn (SurgePrice $surge) => $this->coversModule($surge, $moduleId));

        return $permanent ? $this->payload($permanent) : $this->empty();
    }

    /**
     * Does this row cover the module?
     *
     * TWO CORRECTIONS OVER THE CODE THIS REPLACES, both found in live data:
     *
     *  1. **`module_ids` holds STRINGS.** The admin screen saves `["1", "2"]`, and every caller
     *     passes an integer. `whereJsonContains` is type-sensitive, so the old
     *     `whereJsonContains('module_ids', $moduleId)` matched nothing on this install — a 10%
     *     permanent weekly surge had never once applied. Both types are tried.
     *  2. **An empty or absent `module_ids` means ALL modules** (§9.1), so rows predating the
     *     column keep working instead of silently covering nothing.
     */
    private function coversModule(SurgePrice $surge, mixed $moduleId): bool
    {
        $ids = $surge->module_ids;

        // No list at all means every module.
        if (! is_array($ids) || $ids === []) {
            return true;
        }

        // Loose comparison on purpose: the stored value may be "3" or 3 depending on when the
        // row was written, and both mean the same module.
        foreach ($ids as $id) {
            if ((string) $id === (string) $moduleId) {
                return true;
            }
        }

        return false;
    }

    /** The wire shape every consumer already reads — apps, the Builder and RideShare (N9). */
    private function payload(SurgePrice $surge): array
    {
        return [
            'title' => $surge->surge_price_name,
            'customer_note' => $surge->customer_note,
            'customer_note_status' => $surge->customer_note_status,
            'price' => $surge->price,
            'price_type' => $surge->price_type,
        ];
    }
}
