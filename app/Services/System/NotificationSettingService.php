<?php

namespace App\Services\System;

use App\Models\NotificationSetting;
use App\Support\Notification\SendNotification;
use App\Services\BaseService;

class NotificationSettingService extends BaseService
{
    public function updatePushStatus(mixed $key, mixed $type, mixed $status): void
    {
        $this->settingQuery($key, $type)->update([
            'push_notification_status' => $status,
        ]);
    }
    public function deleteByKeyAndType(mixed $key, mixed $type): void
    {
        $this->settingQuery($key, $type)->delete();
    }
    public function missingForModule(mixed $key, mixed $type, mixed $moduleType): bool
    {
        return $this->settingQuery($key, $type)->where('module_type', $moduleType)->doesntExist();
    }
    public function findOrNewForModule(mixed $key, mixed $type, mixed $moduleType): NotificationSetting
    {
        return NotificationSetting::firstOrNew(['key' => $key, 'type' => $type, 'module_type' => $moduleType]);
    }
    private function settingQuery(mixed $key, mixed $type): mixed
    {
        return NotificationSetting::where('key', $key)->where('type', $type);
    }

    public function upsertAdminSetupData(array $data): bool
    {
        NotificationSetting::upsert($data, ['key', 'type'], ['title', 'mail_status', 'sms_status', 'push_notification_status', 'sub_title']);
        SendNotification::forgetSettings();

        return true;
    }
}
