<?php

namespace App\Services\Marketing;

use App\Models\SmartBanner;
use App\Services\BaseService;
use App\Support\Cache\ApiCache;
use Illuminate\Pagination\LengthAwarePaginator;

class SmartBannerService extends BaseService
{
    private const LIST_COLUMNS = [
        'id',
        'image',
        'position',
        'redirect_type',
        'redirect_target_id',
        'module_id',
    ];

    public function getList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        $zoneIds = $filters['zone_ids'] ?? [];
        $todayDate = $filters['today_date'];
        $nowTime = $filters['now_time'];

        return ApiCache::remember(
            'smart_banners',
            [
                $zoneIds,
                $todayDate,
                $nowTime,
                app()->getLocale(),
                $this->pageSize($paginate),
                $this->pageNumber($paginate),
            ],
            fn () => $this->buildQuery($zoneIds, $todayDate, $nowTime)
                ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate))
        );
    }

    private function buildQuery(array $zoneIds, string $todayDate, string $nowTime): mixed
    {
        return SmartBanner::translateOnly(['title', 'subtitle'])
            ->with(['storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS)])
            ->active()
            ->when(! empty($zoneIds), fn ($query) => $query->whereIn('zone_id', $zoneIds))
            ->where(fn ($query) => $this->activeDayWindow($query, $todayDate))
            ->where(fn ($query) => $this->activeTimeWindow($query, $nowTime))
            ->select(self::LIST_COLUMNS)
            ->orderBy('position')
            ->orderBy('id', 'desc');
    }

    private function activeDayWindow(mixed $query, string $todayDate): void
    {
        $query->where('active_days', 'everyday')
            ->orWhere(function ($window) use ($todayDate) {
                $window->where('active_days', 'custom_date')
                    ->where('start_date', '<=', $todayDate)
                    ->where('end_date', '>=', $todayDate);
            });
    }

    private function activeTimeWindow(mixed $query, string $nowTime): void
    {
        $query->whereNull('start_time')
            ->orWhere(function ($window) use ($nowTime) {
                $window->where('start_time', '<=', $nowTime)
                    ->where(function ($inner) use ($nowTime) {
                        $inner->whereNull('end_time')
                            ->orWhere('end_time', '>=', $nowTime);
                    });
            });
    }
}
