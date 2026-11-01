<?php

namespace App\Services\Item;

use App\Models\CommonCondition;
use App\Services\BaseService;
use App\Traits\System\DropdownDataTrait;
use App\Traits\System\PrioritySettingsTrait;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\System\BusinessSettingService;

class CommonConditionService extends BaseService
{
    use DropdownDataTrait;
    use PrioritySettingsTrait;

    public function getAddData(array $input): array
    {
        return [
            'name' => ($input['name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
        ];
    }

    public function getUpdateData(array $input, object $condition): array
    {
        $slug = Str::slug(($input['name'] ?? null)[array_search('default', ($input['lang'] ?? null))]);

        return [
            'slug' => $condition->slug ? $condition->slug : "{$slug}{$condition->id}",
            'name' => ($input['name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
        ];
    }

    public function getList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->buildConditionQuery($filters)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getActiveList(
        array $paginate = []
    ): LengthAwarePaginator {
        return CommonCondition::translateOnly(['name'])
            ->active()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    private function buildConditionQuery(array $filters): Builder
    {
        $sortBy = $this->findPrioritySetting(name: 'common_condition_sort_by_general', type: 'general');
        $customSort = (app(BusinessSettingService::class)->value('common_condition_default_status') ?? 1) != 1;

        $query = CommonCondition::translateOnly(['name'])
            ->active()
            ->whereHas('items', fn ($q) => $q->whereHas('item', fn ($item) => $item
                ->servableIn($filters['zone_ids'] ?? [], $filters['module_id'] ?? null)
                ->active()
                ->type($filters['type'] ?? 'all')));

        if ($customSort && $sortBy === 'order_count') {
            $query = $this->orderByItemOrderCount($query, $filters);
        } else {
            $query = match ($customSort ? $sortBy : null) {
                'latest' => $query->latest(),
                'oldest' => $query->oldest(),
                'a_to_z' => $query->orderBy('name'),
                'z_to_a' => $query->orderBy('name', 'desc'),
                default => $query,
            };
        }

        return $query->orderBy('common_conditions.id');
    }

    private function orderByItemOrderCount(Builder $query, array $filters): Builder
    {
        $orderCounts = app(ItemService::class)->getCommonConditionOrderCountQuery($filters);

        return $query
            ->leftJoinSub($orderCounts, 'condition_order_counts', 'condition_order_counts.common_condition_id', '=', 'common_conditions.id')
            ->orderByDesc(DB::raw('COALESCE(condition_order_counts.order_count, 0)'));
    }
}
