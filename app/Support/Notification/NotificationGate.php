<?php

namespace App\Support\Notification;

use App\Models\NotificationSetting;
use App\Models\StoreNotificationSetting;
use App\Support\Cache\ApiCache;

class NotificationGate
{
    public const CACHE_KEY = 'notification_settings_matrix';

    private const CHANNELS = ['mail_status', 'push_notification_status', 'sms_status'];

    private static ?array $matrix = null;

    private static array $storeMemo = [];

    public static function allows(mixed $userType, mixed $key, mixed $channel, mixed $storeId = null): int
    {
        $allowed = self::lookup($userType, $key, $channel);

        if ($allowed === 1 && $storeId && $userType === 'store') {
            return self::storeAllows(self::storeSetting($storeId, $key), $channel);
        }

        return $allowed;
    }

    public static function allowsForRental(mixed $userType, mixed $key, mixed $channel, mixed $storeId = null): int
    {
        $allowed = self::lookup($userType, $key, $channel, 'rental');

        if ($allowed === 1 && $storeId && $userType === 'provider') {
            return self::storeAllows(self::storeSettingForRental($storeId, $key), $channel);
        }

        return $allowed;
    }

    public static function allowsForService(mixed $userType, mixed $key, mixed $channel, mixed $storeId = null): int
    {
        $allowed = self::lookup($userType, $key, $channel, 'service');

        if ($allowed === 1 && $storeId && $userType === 'provider') {
            return self::storeAllows(self::storeSettingForService($storeId, $key), $channel);
        }

        return $allowed;
    }

    public static function statusesFor(mixed $userType, mixed $key): ?object
    {
        $row = self::matrix()['base'][self::index($userType, $key)] ?? null;

        return $row ? (object) $row : null;
    }

    public static function storeSetting(mixed $storeId, mixed $key): ?object
    {
        return self::resolveStoreSetting($storeId, $key, fn () => StoreNotificationSettings::install($storeId));
    }

    public static function storeSettingForRental(mixed $storeId, mixed $key): ?object
    {
        return self::resolveStoreSetting($storeId, $key, fn () => StoreNotificationSettings::install($storeId, 'rental'));
    }

    public static function storeSettingForService(mixed $storeId, mixed $key): ?object
    {
        return self::resolveStoreSetting($storeId, $key, fn () => StoreNotificationSettings::install($storeId, 'service'));
    }

    public static function flush(): void
    {
        self::$matrix = null;
        self::$storeMemo = [];

        ApiCache::bust('notification_setting');
    }

    public static function forgetStore(mixed $storeId): void
    {
        unset(self::$storeMemo[(string) $storeId]);
    }

    private static function lookup(mixed $userType, mixed $key, mixed $channel, ?string $moduleType = null): int
    {
        $matrix = self::matrix();

        $row = $moduleType === null
            ? ($matrix['base'][self::index($userType, $key)] ?? null)
            : ($matrix['module'][$moduleType.'|'.self::index($userType, $key)] ?? null);

        return ($row[$channel] ?? null) === 'active' ? 1 : 0;
    }

    private static function storeAllows(?object $setting, mixed $channel): int
    {
        return ($setting?->{$channel} ?? null) === 'active' ? 1 : 0;
    }

    private static function resolveStoreSetting(mixed $storeId, mixed $key, callable $seed): ?object
    {
        $storeKey = (string) $storeId;

        if (! array_key_exists($storeKey, self::$storeMemo)) {
            self::$storeMemo[$storeKey] = self::loadStore($storeId);
        }

        if (! array_key_exists($key, self::$storeMemo[$storeKey])) {
            $seed();
            self::$storeMemo[$storeKey] = self::loadStore($storeId);
        }

        $row = self::$storeMemo[$storeKey][$key] ?? null;

        return $row ? (object) $row : null;
    }

    private static function loadStore(mixed $storeId): array
    {
        return StoreNotificationSetting::where('store_id', $storeId)
            ->get(array_merge(['key'], self::CHANNELS))
            ->keyBy('key')
            ->map(fn ($row) => $row->only(self::CHANNELS))
            ->all();
    }

    private static function matrix(): array
    {
        if (self::$matrix !== null) {
            return self::$matrix;
        }

        return self::$matrix = ApiCache::remember('notification_matrix', self::CACHE_KEY, function () {
            $base = [];
            $module = [];

            $rows = NotificationSetting::orderBy('id')
                ->get(array_merge(['id', 'type', 'key', 'module_type'], self::CHANNELS));

            foreach ($rows as $row) {
                $index = self::index($row->type, $row->key);
                $statuses = $row->only(self::CHANNELS);

                if (! array_key_exists($index, $base)) {
                    $base[$index] = $statuses;
                }

                $module[($row->module_type ?? 'all').'|'.$index] = $statuses;
            }

            return ['base' => $base, 'module' => $module];
        });
    }

    private static function index(mixed $userType, mixed $key): string
    {
        return $userType.'|'.$key;
    }
}
