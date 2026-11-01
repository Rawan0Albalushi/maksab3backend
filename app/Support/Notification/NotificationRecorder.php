<?php

namespace App\Support\Notification;

use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;

class NotificationRecorder
{
    public static function saveForCustomer(mixed $userId, array $data, ?string $orderType = null): void
    {
        self::save(['user_id' => $userId], $data, $orderType);
    }

    public static function saveForVendor(mixed $vendorId, array $data, ?string $orderType = null): void
    {
        self::save(['vendor_id' => $vendorId], $data, $orderType);
    }

    public static function saveForDeliveryMan(mixed $deliveryManId, array $data, ?string $orderType = null): void
    {
        self::save(['delivery_man_id' => $deliveryManId], $data, $orderType);
    }

    public static function saveForServiceman(mixed $servicemanId, array $data, ?string $orderType = null): void
    {
        self::save(['serviceman_id' => $servicemanId], $data, $orderType);
    }

    public static function save(array $owner, array $data, ?string $orderType = null): void
    {
        $ownerId = reset($owner);

        if (blank($ownerId) || is_bool($ownerId) || (is_numeric($ownerId) && (int) $ownerId <= 0)) {
            return;
        }

        try {
            UserNotification::create(array_filter(
                $owner + [
                    'data' => json_encode($data),
                    'order_type' => $orderType,
                ],
                fn ($value) => $value !== null,
            ));
        } catch (\Throwable $exception) {
            Log::channel(NotificationConfig::logChannel())->error('user_notification.write_failed', [
                'owner' => array_key_first($owner),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
