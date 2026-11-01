<?php

namespace App\Services\Store;

use App\Models\StoreSchedule;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;

class StoreScheduleService extends BaseService
{
    public function upsertMany(array $rows): void
    {
        StoreSchedule::upsert($rows, ['store_id', 'day', 'opening_time', 'closing_time']);
    }

    public function find(mixed $id, array $filters = []): ?StoreSchedule
    {
        return StoreSchedule::where('store_id', $filters['store_id'] ?? null)->find($id);
    }

    public function overlaps(array $data): bool
    {
        return StoreSchedule::where('day', $data['day'] ?? null)
            ->where('store_id', $data['store_id'] ?? null)
            ->where(fn ($query) => $query
                ->where(fn ($inner) => $inner
                    ->where('opening_time', '<=', $data['opening_time'])
                    ->where('closing_time', '>=', $data['opening_time']))
                ->orWhere(fn ($inner) => $inner
                    ->where('opening_time', '<=', $data['closing_time'])
                    ->where('closing_time', '>=', $data['closing_time'])))
            ->exists();
    }

    public function create(array $data): int
    {
        return StoreSchedule::insertGetId([
            'store_id' => $data['store_id'],
            'day' => $data['day'],
            'opening_time' => $data['opening_time'],
            'closing_time' => $data['closing_time'],
        ]);
    }

    public function delete(StoreSchedule $schedule): bool
    {
        return (bool) $schedule->delete();
    }

    public function isOpenNow($store): bool
    {
        if (! $store->active) {
            return false;
        }

        if (in_array((string) now()->dayOfWeek, $this->offDays($store), true)) {
            return false;
        }

        return $store->schedules()
            ->where('day', now()->dayOfWeek)
            ->where('opening_time', '<', now()->format('H:i:s'))
            ->where('closing_time', '>', now()->format('H:i:s'))
            ->exists();
    }

    public function scopeOpenNow(Builder $query): Builder
    {
        return $query->where('active', 1)
            ->whereRaw("NOT FIND_IN_SET(?, REPLACE(COALESCE(off_day, ''), ' ', ''))", [now()->dayOfWeek])
            ->whereHas('schedules', fn ($q) => $q
                ->where('day', now()->dayOfWeek)
                ->where('opening_time', '<', now()->format('H:i:s'))
                ->where('closing_time', '>', now()->format('H:i:s')));
    }

    /** @return string[] */
    private function offDays($store): array
    {
        return array_filter(array_map('trim', explode(',', (string) $store->off_day)), 'strlen');
    }
}
