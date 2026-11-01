<?php

namespace App\Services\System;

use App\Models\BusinessSetting;
use App\Models\DataSetting;
use App\Support\Cache\ApiCache;
use App\Support\Notification\NotificationMessages;
use App\Support\Notification\SendNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class MaintenanceModeService
{
    private const SYSTEM_TOPIC_MAP = [
        'user_mobile_app' => 'maintenance_mode_user_app',
        'deliveryman_app' => 'maintenance_mode_deliveryman_app',
        'vendor_app' => 'maintenance_mode_vendor_app',
        'rider_app' => 'maintenance_mode_rider_app',
        'serviceman_app' => 'maintenance_mode_serviceman_app',
    ];

    private const SETUP_KEYS = [
        'maintenance_system_setup',
        'maintenance_duration_setup',
        'maintenance_message_setup',
    ];

    private const LOCK_KEY = 'maintenance_expire_sweep';

    private const LOCK_SECONDS = 60;

    public function isDue(): bool
    {
        if (! ApiCache::has('maintenance')) {
            return false;
        }

        $maintenance = ApiCache::get('maintenance');

        if (! is_array($maintenance) || ! isset($maintenance['start_date'], $maintenance['end_date'])) {
            return false;
        }

        $duration = $maintenance['maintenance_duration'] ?? null;

        if ($duration === null || $duration === 'until_change') {
            return false;
        }

        return Carbon::now()->gt(Carbon::parse($maintenance['end_date']));
    }

    public function expireIfDue(): bool
    {
        if (! $this->isDue()) {
            return false;
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return false;
        }

        try {
            if (! $this->isDue()) {
                return false;
            }

            $systems = $this->configuredSystems();

            ApiCache::forget('maintenance');

            BusinessSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 0]);

            DataSetting::where('type', 'maintenance_mode')->whereIn('key', self::SETUP_KEYS)->delete();

            ApiCache::bust('data_setting');

            $this->notify($systems);

            return true;
        } finally {
            $lock->release();
        }
    }

    private function configuredSystems(): array
    {
        $value = DataSetting::where('type', 'maintenance_mode')
            ->where('key', 'maintenance_system_setup')
            ->value('value');

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? $decoded : [];
    }

    private function notify(array $systems): void
    {
        if ($systems === []) {
            return;
        }

        $notification = NotificationMessages::maintenanceOver();

        foreach (self::SYSTEM_TOPIC_MAP as $system => $topic) {
            if (in_array($system, $systems, true)) {
                SendNotification::pushSilentToTopic($notification, $topic, 'maintenance');
            }
        }
    }
}
