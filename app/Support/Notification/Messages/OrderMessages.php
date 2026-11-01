<?php

namespace App\Support\Notification\Messages;

trait OrderMessages
{
    public static function newOrder($order = null, array $extra = []): array
    {
        return self::make(
            translate('Order notification'),
            translate('New order alert, confirm to proceed'),
            array_merge([
                'type' => 'new_order',
                'order_id' => $order?->id ?? '',
                'module_id' => $order?->module_id ?? '',
                'order_type' => $order?->order_type ?? '',
            ], $extra),
        );
    }
    public static function orderStatus($order, string $description, array $extra = []): array
    {
        return self::make(
            translate('Order notification'),
            $description,
            array_merge(['type' => 'order_status', 'order_id' => $order?->id ?? ''], $extra),
        );
    }
}
