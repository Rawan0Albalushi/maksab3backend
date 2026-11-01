<?php

namespace App\Support\Notification;

use App\Services\System\NotificationMessageService;

class NotificationText
{
    public static function forOrderStatus($order, mixed $status, ?string $userName = null, string $locale = 'en'): mixed
    {
        return self::format(
            value: app(NotificationMessageService::class)->forOrderStatus($status, $order?->module?->module_type, $locale),
            store_name: $order?->store?->name,
            order_id: $order?->id,
            user_name: $userName ?? "{$order?->customer?->f_name} {$order?->customer?->l_name}",
            delivery_man_name: "{$order?->delivery_man?->f_name} {$order?->delivery_man?->l_name}",
        );
    }

    public static function format($value, $user_name = null, $store_name = null, $delivery_man_name = null, $transaction_id = null, $order_id = null, $add_id = null)
    {
        $data = $value;
        if ($value) {
            if ($user_name) {
                $data = str_replace('{userName}', $user_name, $data);
            }

            if ($store_name) {
                $data = str_replace('{storeName}', $store_name, $data);
                $data = str_replace('{providerName}', $store_name, $data);
            }

            if ($delivery_man_name) {
                $data = str_replace('{deliveryManName}', $delivery_man_name, $data);
                $data = str_replace('{riderName}', $delivery_man_name, $data);
                $data = str_replace('{servicemanName}', $delivery_man_name, $data);
            }

            if ($transaction_id) {
                $data = str_replace('{transactionId}', $transaction_id, $data);
            }

            if ($order_id) {
                $data = str_replace('{orderId}', $order_id, $data);
                $data = str_replace('{tripId}', $order_id, $data);
                $data = str_replace('{rideId}', $order_id, $data);
                $data = str_replace('{bookingId}', $order_id, $data);
            }
            if ($add_id) {
                $data = str_replace('{advertisementId}', $add_id, $data);
            }
        }

        return $data;
    }

    public static function forDeliveryman(?string $value, $deliveryMan = null, bool $includeRiderOption = false): ?string
    {
        if (! $value) {
            return $value;
        }

        if ($includeRiderOption && self::rideShareLabelsEnabled()) {
            return self::replaceByContext($value, 'combined');
        }

        if (
            $deliveryMan
            && self::rideShareLabelsEnabled()
            && (int) data_get($deliveryMan, 'is_ride', 0) === 1
            && (int) data_get($deliveryMan, 'is_delivery', 0) !== 1
        ) {
            return self::replaceByContext($value, 'rider');
        }

        return $value;
    }

    public static function rideShareLabelsEnabled(): bool
    {
        return function_exists('addon_published_status') && addon_published_status('RideShare') == 1;
    }

    private static function replaceByContext(string $value, string $mode): string
    {
        $replacements = match ($mode) {
            'combined' => [
                '/\bdelivery men\b/u' => 'delivery men / riders',
                '/\bDelivery Men\b/u' => 'Delivery Men / Riders',
                '/\bDELIVERY MEN\b/u' => 'DELIVERY MEN / RIDERS',
                '/\bdeliveryman\b/u' => 'deliveryman / rider',
                '/\bDeliveryman\b/u' => 'Deliveryman / Rider',
                '/\bDELIVERYMAN\b/u' => 'DELIVERYMAN / RIDER',
                '/\bdelivery man\b/u' => 'delivery man / rider',
                '/\bDelivery Man\b/u' => 'Delivery Man / Rider',
                '/\bDELIVERY MAN\b/u' => 'DELIVERY MAN / RIDER',
                '/\bdeliverymen\b/u' => 'deliverymen / riders',
                '/\bDeliverymen\b/u' => 'Deliverymen / Riders',
                '/\bDELIVERYMEN\b/u' => 'DELIVERYMEN / RIDERS',
            ],
            default => [
                '/\bdelivery men\b/u' => 'riders',
                '/\bDelivery Men\b/u' => 'Riders',
                '/\bDELIVERY MEN\b/u' => 'RIDERS',
                '/\bdeliveryman\b/u' => 'rider',
                '/\bDeliveryman\b/u' => 'Rider',
                '/\bDELIVERYMAN\b/u' => 'RIDER',
                '/\bdelivery man\b/u' => 'rider',
                '/\bDelivery Man\b/u' => 'Rider',
                '/\bDELIVERY MAN\b/u' => 'RIDER',
                '/\bdeliverymen\b/u' => 'riders',
                '/\bDeliverymen\b/u' => 'Riders',
                '/\bDELIVERYMEN\b/u' => 'RIDERS',
            ],
        };

        foreach ($replacements as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value);
        }

        return $value;
    }
}
