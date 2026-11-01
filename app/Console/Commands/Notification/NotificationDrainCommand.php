<?php

namespace App\Console\Commands\Notification;

use App\Support\Notification\NotificationConfig;
use App\Support\Notification\NotificationMode;
use Illuminate\Console\Command;

class NotificationDrainCommand extends Command
{
    protected $signature = 'notification:drain {--force : Drain even when the mode is not cron}';

    protected $description = 'Drain queued notifications once and exit, for servers without a long-running worker';

    public function handle(): int
    {
        $mode = NotificationConfig::mode();

        if (! $mode->isDeferred() && ! $this->option('force')) {
            $this->line("Notification mode is {$mode->value}, nothing is queued. Skipping.");

            return self::SUCCESS;
        }

        return $this->call('queue:work', [
            'connection' => NotificationConfig::connection(),
            '--queue' => implode(',', NotificationConfig::lanes()),
            '--stop-when-empty' => true,
            '--max-time' => (int) config('notification.drain.max_time', 55),
            '--sleep' => (int) config('notification.drain.sleep', 1),
            '--tries' => NotificationConfig::tries(),
        ]);
    }
}
