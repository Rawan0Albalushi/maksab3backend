<?php

namespace App\Support\Notification;

use App\Models\BusinessSetting;
use App\Services\System\BusinessSettingService;

class NotificationConfig
{
    private static ?NotificationMode $memo = null;

    public static function mode(): NotificationMode
    {
        if (self::$memo instanceof NotificationMode) {
            return self::$memo;
        }

        $stored = null;

        try {
            $stored = app(BusinessSettingService::class)->value(config('notification.setting_key'), false);
        } catch (\Throwable) {
            $stored = null;
        }

        return self::$memo = NotificationMode::parse($stored ?: config('notification.mode'));
    }

    public static function setMode(NotificationMode $mode): void
    {
        BusinessSetting::updateOrCreate(
            ['key' => config('notification.setting_key')],
            ['value' => $mode->value],
        );

        self::$memo = $mode;
    }

    public static function forget(): void
    {
        self::$memo = null;
    }

    public static function useMode(NotificationMode $mode): void
    {
        self::$memo = $mode;
    }

    public static function connection(): string
    {
        return config('notification.connections.'.self::mode()->value, 'sync');
    }

    public static function queueFor(string $lane): string
    {
        return config('notification.queues.'.$lane, config('notification.queues.mail'));
    }

    public static function lanes(): array
    {
        return array_values(array_unique([
            self::queueFor('push'),
            self::queueFor('mail'),
            self::queueFor('bulk'),
        ]));
    }

    public static function tries(): int
    {
        return (int) config('notification.retry.tries', 3);
    }

    public static function backoff(): array
    {
        return (array) config('notification.retry.backoff', [10, 60, 300]);
    }

    public static function timeout(): int
    {
        return (int) config('notification.retry.timeout', 30);
    }

    public static function logChannel(): string
    {
        return (string) config('notification.log_channel', 'notifications');
    }
}
