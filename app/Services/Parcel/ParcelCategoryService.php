<?php

namespace App\Services\Parcel;

use App\Models\ParcelCategory;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class ParcelCategoryService extends BaseService
{
    public function getList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return ParcelCategory::translateOnly(['name', 'description'])
            ->with(['storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS)])
            ->when($filters['module_id'] ?? null, fn ($query, $moduleId) => $query->module($moduleId))
            ->active()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function find(mixed $id): mixed
    {
        return ParcelCategory::find($id);
    }

}
