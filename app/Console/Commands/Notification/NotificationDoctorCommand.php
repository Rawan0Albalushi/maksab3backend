<?php

namespace App\Console\Commands\Notification;

use App\Support\Notification\Fcm\FcmCredentials;
use App\Support\Notification\NotificationConfig;
use App\Support\Notification\NotificationMode;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\System\BusinessSettingService;

class NotificationDoctorCommand extends Command
{
    protected $signature = 'notification:doctor';

    protected $description = 'Check that the notification delivery pipeline is correctly configured';

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(Schedule $schedule): int
    {
        $mode = NotificationConfig::mode();

        $this->components->info("Notification mode: {$mode->value} ({$mode->label()}), connection: ".NotificationConfig::connection());

        $this->checkTables($mode);
        $this->checkMail();
        $this->checkPush();
        $this->checkDelivery($mode, $schedule);

        $this->newLine();

        if ($this->failures > 0) {
            $this->components->error("{$this->failures} check(s) failed, {$this->warnings} warning(s).");

            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $this->components->warn("All required checks passed with {$this->warnings} warning(s).");

            return self::SUCCESS;
        }

        $this->components->info('All checks passed.');

        return self::SUCCESS;
    }

    private function checkTables(NotificationMode $mode): void
    {
        $this->assert(
            Schema::hasTable('failed_jobs'),
            'failed_jobs table exists',
            'failed_jobs table is missing. Run: php artisan migrate',
        );

        if ($mode->isDeferred()) {
            $this->assert(
                Schema::hasTable('jobs'),
                'jobs table exists',
                'jobs table is missing but the mode is '.$mode->value.'. Run: php artisan migrate',
            );

            $this->assert(
                Schema::hasTable('job_batches'),
                'job_batches table exists',
                'job_batches table is missing, batched notifications will fail. Run: php artisan migrate',
            );
        }
    }

    private function checkMail(): void
    {
        if (! config('mail.status')) {
            $this->warn('  ! Mail is disabled in business settings, no email will be sent.');
            $this->warnings++;

            return;
        }

        $host = config('mail.host');
        $from = config('mail.from.address');

        $this->assert((bool) $host, 'Mail host configured', 'Mail is enabled but mail.host is empty.');
        $this->assert((bool) $from, 'Mail from-address configured', 'Mail is enabled but mail.from.address is empty.');
    }

    private function checkPush(): void
    {
        $config = (array) app(BusinessSettingService::class)->value('push_notification_service_file_content');

        if (! data_get($config, 'project_id')) {
            $this->warn('  ! No FCM service account configured, no push notification will be sent.');
            $this->warnings++;

            return;
        }

        $this->assert(true, 'FCM service account present (project '.data_get($config, 'project_id').')', '');

        $token = FcmCredentials::accessToken($config);

        $this->assert(
            (bool) $token,
            'FCM OAuth token obtainable',
            'Could not obtain an FCM OAuth token. Check client_email and private_key in the service account.',
        );
    }

    private function checkDelivery(NotificationMode $mode, Schedule $schedule): void
    {
        if (! $mode->isDeferred()) {
            return;
        }

        if ($mode === NotificationMode::Cron) {
            $registered = collect($schedule->events())
                ->contains(fn ($event) => str_contains((string) $event->command, 'notification:drain'));

            $this->assert(
                $registered,
                'notification:drain is registered with the scheduler',
                'notification:drain is not registered. Check ScheduleServiceProvider.',
            );

            $this->line('  - The OS crontab cannot be verified from PHP. Confirm this line exists:');
            $this->line('      * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');
        }

        if (! Schema::hasTable('jobs')) {
            return;
        }

        $oldest = DB::table('jobs')
            ->whereIn('queue', NotificationConfig::lanes())
            ->min('created_at');

        if ($oldest === null) {
            $this->assert(true, 'No notification backlog', '');

            return;
        }

        $age = Carbon::createFromTimestamp($oldest)->diffInMinutes(now());

        if ($age >= 5) {
            $this->assert(
                false,
                '',
                "Oldest queued notification is {$age} minutes old. The ".($mode === NotificationMode::Cron ? 'scheduler' : 'worker').' does not appear to be running.',
            );

            return;
        }

        $this->assert(true, "Notification backlog is draining (oldest {$age}m)", '');
    }

    private function assert(bool $passed, string $pass, string $fail): void
    {
        if ($passed) {
            $this->line("  <fg=green>✓</> {$pass}");

            return;
        }

        $this->line("  <fg=red>✗</> {$fail}");
        $this->failures++;
    }
}
