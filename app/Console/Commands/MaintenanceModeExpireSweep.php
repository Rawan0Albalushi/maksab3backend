<?php

namespace App\Console\Commands;

use App\Services\System\MaintenanceModeService;
use Illuminate\Console\Command;

class MaintenanceModeExpireSweep extends Command
{
    protected $signature = 'maintenance:expire-sweep';

    protected $description = 'Turn maintenance mode off once its scheduled end date has passed and notify the apps';

    public function handle(MaintenanceModeService $maintenance): void
    {
        $this->info($maintenance->expireIfDue()
            ? 'Maintenance mode expired and cleared.'
            : 'Maintenance mode has no expired schedule. Nothing to do.');
    }
}
