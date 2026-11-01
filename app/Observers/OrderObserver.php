<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderReference;
use App\Services\Customer\PersonalizationService;

class OrderObserver
{
    public function created(Order $order): void
    {
        $OrderReference = new OrderReference();
        $OrderReference->order_id = $order->id;
        $OrderReference->save();
    }

    public function updated(Order $order): void
    {
        if ($order->isDirty('order_status') && $order->order_status === 'delivered' && $order->user_id) {
            $details = OrderDetail::where('order_id', $order->id)->whereNotNull('item_id')->get();
            foreach ($details as $detail) {
                app(PersonalizationService::class)->recordItemAction($order->user_id, $detail->item_id, 'order');
            }
        }
    }

    public function deleted(Order $order): void
    {
    }

    public function restored(Order $order): void
    {
    }

    public function forceDeleted(Order $order): void
    {
    }
}
