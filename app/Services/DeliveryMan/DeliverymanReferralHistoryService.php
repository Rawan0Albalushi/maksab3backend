<?php

namespace App\Services\DeliveryMan;

use App\Models\DeliverymanReferralHistory;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class DeliverymanReferralHistoryService extends BaseService
{
    private const LIST_COLUMNS = ['id', 'transaction_id', 'amount', 'created_at'];

    public function earnedTotal(array $filters = []): float
    {
        return (float) $this->baseQuery($filters)->sum('amount');
    }

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->select(self::LIST_COLUMNS)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    private function baseQuery(array $filters): mixed
    {
        return DeliverymanReferralHistory::where('delivery_man_id', $filters['delivery_man_id'] ?? null)
            ->applyDateFilter($filters['date_range'] ?? null, $filters['start_date'] ?? null, $filters['end_date'] ?? null);
    }
}
