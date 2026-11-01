<?php

namespace App\Services\Marketing;

use App\Models\ModuleWiseWhyChoose;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class ModuleWiseWhyChooseService extends BaseService
{
    private const LIST_COLUMNS = ['id', 'title', 'short_description', 'image'];

    public function getList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return ModuleWiseWhyChoose::translateOnly(['title', 'short_description'])
            ->with(['storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS)])
            ->active()
            ->where('module_id', $filters['module_id'] ?? null)
            ->select(self::LIST_COLUMNS)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
