<?php

namespace App\Console\Commands\Notification;

use App\Support\Notification\NotificationConfig;
use App\Support\Notification\NotificationMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationModeCommand extends Command
{
    protected $signature = 'notification:mode {mode? : after_response, cron, queue or sync}';

    protected $description = 'Show or change how email and push notifications are delivered';

    public function handle(): int
    {
        $requested = $this->argument('mode');

        if ($requested === null) {
            return $this->showStatus();
        }

        $mode = NotificationMode::tryFrom(strtolower($requested));

        if (! $mode instanceof NotificationMode) {
            $this->error("Unknown mode '{$requested}'. Use one of: ".implode(', ', config('notification.available_modes', [])).'.');

            return self::FAILURE;
        }

        NotificationConfig::setMode($mode);

        $this->info("Notification mode set to {$mode->value} ({$mode->label()}).");
        $this->newLine();

        $this->printModeInstructions($mode);

        return self::SUCCESS;
    }

    private function showStatus(): int
    {
        $mode = NotificationConfig::mode();

        $this->components->twoColumnDetail('Mode', "{$mode->value} ({$mode->label()})");
        $this->components->twoColumnDetail('Connection', NotificationConfig::connection());
        $this->components->twoColumnDetail('Queues', implode(', ', NotificationConfig::lanes()));
        $this->components->twoColumnDetail('Retry', NotificationConfig::tries().' tries, backoff '.implode('/', NotificationConfig::backoff()).'s');
        $this->components->twoColumnDetail('Log channel', NotificationConfig::logChannel());

        $this->newLine();
        $this->components->twoColumnDetail('Pending jobs', (string) $this->countJobs());
        $this->components->twoColumnDetail('Failed jobs', (string) $this->countFailedJobs());

        $this->newLine();
        $this->printModeInstructions($mode);

        return self::SUCCESS;
    }

    private function printModeInstructions(NotificationMode $mode): void
    {
        if ($mode === NotificationMode::Sync) {
            $this->line('  Notifications are delivered during the request, so the response waits for them.');
            $this->line('  Switch to after_response for a faster response with no extra setup.');

            return;
        }

        if ($mode === NotificationMode::AfterResponse) {
            $this->line('  The response is flushed to the browser first, then notifications are sent.');
            $this->line('  No cron, no worker, no Supervisor required.');
            $this->line('  Note: there is no automatic retry in this mode, and the PHP worker stays busy');
            $this->line('  until delivery finishes, so it does not free the process for the next request.');

            return;
        }

        if ($mode === NotificationMode::Cron) {
            $this->line('  Notifications are queued and drained by the scheduler every minute.');
            $this->line('  Make sure this crontab entry exists:');
            $this->newLine();
            $this->line('    * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');

            return;
        }

        $this->line('  Notifications are queued and processed by a long-running worker.');
        $this->line('  Run a worker with Supervisor:');
        $this->newLine();
        $this->line('    [program:notification-worker]');
        $this->line('    process_name=%(program_name)s_%(process_num)02d');
        $this->line('    command=php '.base_path().'/artisan queue:work '.NotificationConfig::connection().' --queue='.implode(',', NotificationConfig::lanes()).' --tries='.NotificationConfig::tries().' --sleep=3');
        $this->line('    autostart=true');
        $this->line('    autorestart=true');
        $this->line('    numprocs=1');
        $this->line('    redirect_stderr=true');
        $this->line('    stdout_logfile='.storage_path('logs/notification-worker.log'));
    }

    private function countJobs(): int
    {
        return Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
    }

    private function countFailedJobs(): int
    {
        return Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
    }
}
