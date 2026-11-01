<?php

namespace App\Services\Item;

use App\Models\AddonCategory;
use App\Services\BaseService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class AddonCategoryService extends BaseService
{
    private const STATUS_ACTIVE = 1;

    public function find(mixed $id, array $with = []): ?AddonCategory
    {
        return AddonCategory::with($with)->find($id);
    }

    public function getList(
        array $filters = [],
        array $with = [],
        array $withCount = [],
        bool $withTrashed = false,
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->buildQuery($filters, $with, $withCount)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    private function buildQuery(array $filters = [], array $with = [], array $withCount = []): Builder
    {
        return AddonCategory::translateOnly(['name'])
            ->with($with)
            ->withCount($withCount)
            ->select('id', 'name')
            ->where(function ($query) use ($filters) {
                $query->where('module_id', $filters['module_id'] ?? null)->orWhereNull('module_id');
            })
            ->where('status', self::STATUS_ACTIVE)
            ->latest();
    }
}
