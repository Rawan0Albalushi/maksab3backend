<?php

namespace App\Services\Order;

use App\Models\OrderPayment;
use App\Services\BaseService;
use App\Traits\Order\OrderPaymentsTrait;

class OrderPaymentService extends BaseService
{
    use OrderPaymentsTrait;

    public function markUnpaidAsCashOnDelivery(mixed $orderId): void
    {
        OrderPayment::where('order_id', $orderId)
            ->where('payment_status', 'unpaid')
            ->update(['payment_method' => 'cash_on_delivery']);
    }

    public function findUnpaidForOrder(mixed $orderId): mixed
    {
        return OrderPayment::where('payment_status', 'unpaid')->where('order_id', $orderId)->first();
    }

    public function hasCashOnDeliveryForOrder(mixed $orderId): bool
    {
        return OrderPayment::where('payment_method', 'cash_on_delivery')->where('order_id', $orderId)->exists();
    }
}
