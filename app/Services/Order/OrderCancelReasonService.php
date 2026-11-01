<?php

namespace App\Services\Order;

use App\Models\OrderCancelReason;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderCancelReasonService extends BaseService
{
    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return OrderCancelReason::where('status', 1)
            ->when($filters['user_type'] ?? null, fn ($query) => $query->where('user_type', $filters['user_type']))
            ->paginate($this->pageSize($paginate), ['id', 'reason', 'user_type', 'status'], 'page', $this->pageNumber($paginate));
    }
}
