<?php

namespace App\Services\System;

use App\Models\ProCustomerFaq;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class ProCustomerFaqService extends BaseService
{
    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return ProCustomerFaq::where('status', 1)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($group) use ($search) {
                    $group->where('question', 'like', "%{$search}%")
                        ->orWhere('answer', 'like', "%{$search}%");
                });
            })
            ->orderBy('priority')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
