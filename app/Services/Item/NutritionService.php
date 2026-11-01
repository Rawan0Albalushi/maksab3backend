<?php

namespace App\Services\Item;

use App\Models\Nutrition;
use App\Services\BaseService;
use App\Traits\Item\ResolvesNamedIdsTrait;
use Illuminate\Pagination\LengthAwarePaginator;

class NutritionService extends BaseService
{
    use ResolvesNamedIdsTrait;

    protected function namedIdModel(): string
    {
        return Nutrition::class;
    }

    protected function namedIdColumn(): string
    {
        return 'nutrition';
    }

    public function getByIds(array $ids, array $columns = ['*']): mixed
    {
        return Nutrition::whereIn('id', $ids)->get($columns);
    }

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return Nutrition::when($filters['search'] ?? null, fn ($query, $search) => $query->where('nutrition', 'like', "%{$search}%"))
            ->select(['id', 'nutrition'])
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
