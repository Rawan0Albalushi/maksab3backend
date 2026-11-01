<?php

namespace App\Services\Order;

use App\Models\OrderEditLog;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderEditLogService extends BaseService
{
    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return OrderEditLog::where('order_id', $filters['order_id'] ?? null)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function record(int $orderId, string $log, string $editedBy): void
    {
        OrderEditLog::create([
            'order_id' => $orderId,
            'log' => $log,
            'edited_by' => $editedBy,
        ]);
    }
}
