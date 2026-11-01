<?php

namespace App\Services\Order;

use App\Models\RefundReason;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class RefundReasonService extends BaseService
{
    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return RefundReason::where('status', 1)
            ->paginate($this->pageSize($paginate), ['id', 'reason', 'status'], 'page', $this->pageNumber($paginate));
    }
}
