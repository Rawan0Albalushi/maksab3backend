<?php

namespace App\Services\Order;

use App\Models\OrderProDiscount;
use App\Services\BaseService;

class OrderProDiscountService extends BaseService
{
    public function savingsTotals(mixed $userId, mixed $transactionId): mixed
    {
        return OrderProDiscount::where('user_id', $userId)
            ->where('transaction_id', $transactionId)
            ->selectRaw('COALESCE(SUM(amount_saved), 0) + COALESCE(SUM(delivery_fee_reduction_amount), 0) as total_saved')
            ->selectRaw('COUNT(*) as total_orders')
            ->first();
    }

    public function create(array $data): OrderProDiscount
    {
        return OrderProDiscount::create($data);
    }

    public function deleteForOrder(mixed $orderId): void
    {
        OrderProDiscount::where('order_id', $orderId)->delete();
    }
}
