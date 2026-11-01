<?php

namespace App\Providers;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\DataSetting;
use App\Models\NotificationMessage;
use App\Support\DisbursementScheduleResolver;
use App\Support\Notification\NotificationConfig;
use App\Support\Notification\NotificationMode;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ScheduleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $schedule->command('dm:disbursement')
                ->everyMinute()
                ->when(fn () => $this->disbursementShouldFire('dm'))
                ->runInBackground()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/schedule.log'));

            $schedule->command('store:disbursement')
                ->everyMinute()
                ->when(fn () => $this->disbursementShouldFire('store'))
                ->runInBackground()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/schedule.log'));

            $schedule->command('customer-subscription:reminder')
                ->everyMinute()
                ->when(fn () => $this->subscriptionShouldFire())
                ->runInBackground()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/schedule.log'));

            $schedule->command('store-subscription:validity-sweep')
                ->dailyAt('00:05')
                ->runInBackground()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/schedule.log'));

            $schedule->command('customer-subscription:expire')
                ->dailyAt('00:10')
                ->runInBackground()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/schedule.log'));

            $schedule->command('monthly-order-reminder:dispatch')
                ->everyFiveMinutes()
                ->runInBackground()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/schedule.log'));

            $schedule->command('maintenance:expire-sweep')
                ->everyMinute()
                ->runInBackground()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/schedule.log'));

            $schedule->command('notification:drain')
                ->everyMinute()
                ->when(fn () => NotificationConfig::mode() === NotificationMode::Cron)
                ->runInBackground()
                ->withoutOverlapping()
                ->appendOutputTo(storage_path('logs/notifications.log'));
        });
    }

    private function disbursementShouldFire(string $prefix): bool
    {
        if (Helpers::get_business_settings('disbursement_type', false) !== 'automated') {
            return false;
        }

        $expression = $prefix === 'dm'
            ? DisbursementScheduleResolver::forDeliveryMan()
            : DisbursementScheduleResolver::forStore();

        return (new CronExpression($expression))->isDue(now()->setSecond(0));
    }

    private function subscriptionShouldFire(): bool
    {
        $beforeTime = (int) (DataSetting::where(['key' => 'subscription_reminder_before_time', 'type' => 'notification_settings'])->value('value') ?? 0);
        if ($beforeTime <= 0) {
            return false;
        }

        $enabled = NotificationMessage::where('key', 'subscription_expire_reminder')
            ->where('status', 1)
            ->exists();
        if (!$enabled) {
            return false;
        }

        $unit = DataSetting::where(['key' => 'subscription_reminder_before', 'type' => 'notification_settings'])->value('value') ?? 'days';
        $now = now();

        return match ($unit) {
            'min'   => true,
            'hour'  => $now->minute === 0,
            default => $now->hour === 0 && $now->minute === 0,
        };
    }
}
