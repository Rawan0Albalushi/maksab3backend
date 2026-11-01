<?php

namespace App\Services\DeliveryMan;

use App\Models\DeliverymanLoyaltyPointHistory;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Traits\DeliveryMan\DeliverymanLoyaltyPointTrait;

class DeliverymanLoyaltyPointHistoryService extends BaseService
{
    use DeliverymanLoyaltyPointTrait;

    private const REPORT_COLUMNS = ['id', 'transaction_id', 'transaction_type', 'converted_amount', 'point', 'created_at'];

    private const LIST_COLUMNS = [
        'id', 'transaction_id', 'transaction_type', 'converted_amount', 'point', 'created_at', 'reference', 'point_conversion_type',
    ];

    public function convertedTotal(array $filters = []): float
    {
        return (float) $this->baseQuery($filters)->where('point_conversion_type', 'debit')->sum('converted_amount');
    }

    public function getConvertedList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->where('point_conversion_type', 'debit')
            ->select(self::REPORT_COLUMNS)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $type = $filters['type'] ?? 'both';

        return $this->baseQuery($filters)
            ->when($type !== 'both', fn ($query) => $query->where('point_conversion_type', $type))
            ->select(self::LIST_COLUMNS)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function convert(mixed $deliveryManId, mixed $points): array
    {
        $result = $this->recordDeliverymanLoyaltyPoint(
            deliveryManId: $deliveryManId,
            amount: $points,
            transactionType: 'converted_to_wallet',
            pointConversionType: 'debit',
            reference: null
        );

        return data_get($result, 'status_code') === 403
            ? (array) $result
            : ['status_code' => 200];
    }

    private function baseQuery(array $filters): Builder
    {
        return DeliverymanLoyaltyPointHistory::where('delivery_man_id', $filters['delivery_man_id'] ?? null)
            ->applyDateFilter($filters['date_range'] ?? null, $filters['start_date'] ?? null, $filters['end_date'] ?? null);
    }
}
