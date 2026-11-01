<?php

namespace App\Services\Zone;

use Illuminate\Support\Facades\DB;

use App\Models\ModuleZone;
use App\Services\BaseService;

class ModuleZoneService extends BaseService
{
    /**
     * The modules connected to a zone in Zone Setup → Connect Module.
     *
     * A zone can only be priced for a module it actually serves, so this is the first filter on
     * every module picker in the delivery suite. Reads the pivot directly rather than through
     * Zone::modules(), which drags nine pricing columns along for what is an id lookup.
     */
    public function connectedModuleIds(mixed $zoneId): array
    {
        if (empty($zoneId)) {
            return [];
        }

        return DB::table('module_zone')
            ->where('zone_id', $zoneId)
            ->pluck('module_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function findForModuleAndZone(mixed $moduleId, mixed $zoneId): mixed
    {
        return $this->forModuleAndZoneQuery($moduleId, $zoneId)->first();
    }

    private function forModuleAndZoneQuery(mixed $moduleId, mixed $zoneId): mixed
    {
        return ModuleZone::query()
            ->where('module_id', $moduleId)
            ->where('zone_id', $zoneId);
    }

}
