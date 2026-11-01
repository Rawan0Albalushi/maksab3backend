<?php

namespace App\Services\Parcel;

use App\Models\ParcelCancellation;
use App\Services\BaseService;
use App\Traits\Parcel\ParcelOrderCancellationTrait;
use Carbon\Carbon;

class ParcelCancellationService extends BaseService
{
    use ParcelOrderCancellationTrait;

    public function deliveryManReturnFeeTotals(mixed $deliveryManId): array
    {
        $row = ParcelCancellation::whereHas('order', fn ($query) => $query->where('delivery_man_id', $deliveryManId))
            ->where('return_fee_payment_status', 'paid')
            ->selectRaw('
                SUM(CASE WHEN DATE(updated_at) = ? THEN return_fee ELSE 0 END) as today,
                SUM(CASE WHEN updated_at BETWEEN ? AND ? THEN return_fee ELSE 0 END) as week,
                SUM(CASE WHEN updated_at BETWEEN ? AND ? THEN return_fee ELSE 0 END) as month
            ', [
                Carbon::today(),
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ])
            ->first();

        return [
            'today' => (float) $row->today,
            'week' => (float) $row->week,
            'month' => (float) $row->month,
        ];
    }

    public function findOrNewForOrder(mixed $orderId): ParcelCancellation
    {
        return ParcelCancellation::where('order_id', $orderId)->firstOrNew();
    }

}
