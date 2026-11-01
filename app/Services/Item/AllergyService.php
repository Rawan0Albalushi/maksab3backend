<?php

namespace App\Services\Item;

use App\Models\Allergy;
use App\Services\BaseService;
use App\Traits\Item\ResolvesNamedIdsTrait;
use Illuminate\Pagination\LengthAwarePaginator;

class AllergyService extends BaseService
{
    use ResolvesNamedIdsTrait;

    protected function namedIdModel(): string
    {
        return Allergy::class;
    }

    protected function namedIdColumn(): string
    {
        return 'allergy';
    }

    public function getByIds(array $ids, array $columns = ['*']): mixed
    {
        return Allergy::whereIn('id', $ids)->get($columns);
    }

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return Allergy::when($filters['search'] ?? null, fn ($query, $search) => $query->where('allergy', 'like', "%{$search}%"))
            ->select(['id', 'allergy'])
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
