<?php

namespace App\Traits\Store;

use App\CentralLogics\Helpers;
use App\Services\Store\StoreService;
use App\Services\Payment\StoreSubscriptionService;

trait StoreSubscriptionValidityTrait
{
    public function expireDueStoreSubscriptions(): int
    {
        static $running = false;
        if ($running) {
            return 0;
        }
        $running = true;

        try {
            return $this->doExpireDueStoreSubscriptions();
        } finally {
            $running = false;
        }
    }

    private function doExpireDueStoreSubscriptions(): int
    {
        $currentDate = date('Y-m-d');

        $lastRun = Helpers::getSettingsDataFromConfig(settings: 'check_daily_subscription_validity_check');

        if (! $lastRun) {
            Helpers::insert_business_settings_key('check_daily_subscription_validity_check', $currentDate);

            return 0;
        }

        if ($lastRun->value == $currentDate) {
            return 0;
        }

        $unsubscribed = app(StoreService::class)->unsubscribeExpired($currentDate);

        app(StoreSubscriptionService::class)->closeExpired($currentDate);

        $lastRun->value = $currentDate;
        $lastRun->save();

        return $unsubscribed;
    }
}
