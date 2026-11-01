<?php

namespace App\Console\Commands;

use App\Models\Zone;
use App\Services\Zone\ZoneService;
use Illuminate\Console\Command;

/**
 * What the readiness migration will do, before it does it.
 *
 * Z3 takes zones offline, which customers feel, so the change is readable first.
 */
class ZoneReadinessReport extends Command
{
    protected $signature = 'zones:readiness-report';

    protected $description = 'Show which zones have no module carrying both a delivery rule and an ETA configuration';

    public function handle(): int
    {
        $zones = Zone::all();
        $readiness = app(ZoneService::class)->readinessFor($zones);
        $serving = Zone::effective()->pluck('id')->all();

        $rows = [];
        $willSwitchOff = 0;

        foreach ($zones as $zone) {
            $gaps = $readiness[$zone->id]['gaps'];
            $offline = (int) $zone->status === 1 && $gaps !== [];
            $willSwitchOff += $offline ? 1 : 0;

            $rows[] = [
                $zone->id,
                mb_substr($zone->name, 0, 22),
                $zone->status ? 'ON' : 'off',
                implode(',', $zone->ruledModuleIds()) ?: '—',
                implode(',', $zone->timedModuleIds()) ?: '—',
                implode(',', array_intersect($zone->ruledModuleIds(), $zone->timedModuleIds())) ?: '—',
                in_array($zone->id, $serving) ? 'yes' : 'no',
                $offline ? 'SWITCHED OFF' : '',
            ];
        }

        $this->table(
            ['id', 'zone', 'status', 'modules with a rule', 'with an ETA', 'with BOTH', 'serving now', 'migration'],
            $rows,
        );

        $this->newLine();
        $this->line("Zones the migration will switch off: <options=bold>{$willSwitchOff}</>");

        if ($willSwitchOff > 0) {
            $this->warn('Those zones stop being offered to customers until one of their modules has both setups.');
        }

        return self::SUCCESS;
    }
}
