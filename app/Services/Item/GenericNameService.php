<?php

namespace App\Services\Item;

use App\Models\GenericName;
use App\Services\BaseService;
use App\Traits\Item\ResolvesNamedIdsTrait;
use Illuminate\Pagination\LengthAwarePaginator;

class GenericNameService extends BaseService
{
    use ResolvesNamedIdsTrait;

    protected function namedIdModel(): string
    {
        return GenericName::class;
    }

    protected function namedIdColumn(): string
    {
        return 'generic_name';
    }

    public function getByIds(array $ids, array $columns = ['*']): mixed
    {
        return GenericName::whereIn('id', $ids)->get($columns);
    }

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return GenericName::when($filters['search'] ?? null, fn ($query, $search) => $query->where('generic_name', 'like', "%{$search}%"))
            ->select(['id', 'generic_name'])
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
