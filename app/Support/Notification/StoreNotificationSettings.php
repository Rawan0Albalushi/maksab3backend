<?php

namespace App\Support\Notification;

use App\Models\StoreNotificationSetting;
use App\Traits\Notification\NotificationDataSetUpTrait;

class StoreNotificationSettings
{
    use NotificationDataSetUpTrait;

    private const UPDATE_COLUMNS = ['title', 'mail_status', 'sms_status', 'push_notification_status', 'sub_title'];

    public static function install(mixed $storeId, string $moduleType = 'core'): bool
    {
        [$rows, $uniqueBy] = match ($moduleType) {
            'rental' => [self::getRentalStoreNotificationSetupData($storeId), ['key', 'store_id', 'module_type']],
            'service' => [self::getServiceStoreNotificationSetupData($storeId), ['key', 'store_id', 'module_type']],
            default => [self::getStoreNotificationSetupData($storeId), ['key', 'store_id']],
        };

        StoreNotificationSetting::upsert($rows, $uniqueBy, self::UPDATE_COLUMNS);

        NotificationGate::forgetStore($storeId);

        return true;
    }
}
