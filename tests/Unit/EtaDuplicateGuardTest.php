<?php

namespace Tests\Unit;

use App\Exceptions\DuplicateEtaConfigurationException;
use App\Models\EtaConfiguration;
use App\Services\Zone\EtaConfigurationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DESIGN RULE E1 — one ETA configuration per (zone, module).
 *
 * The form request checks this, but a form request is a read with a write after it and nothing in
 * between: two saves for the same pair arriving together both read "no conflict" and both insert.
 * Three concurrent POSTs for one pair produced three configurations. The service now re-checks
 * inside its own transaction behind lockForUpdate(), so the second caller waits, sees the first,
 * and is refused.
 *
 * These tests exercise the SERVICE directly, which is the path a race actually takes — validation
 * has already passed by then, so a test that goes through the request would prove nothing about it.
 */
class EtaDuplicateGuardTest extends TestCase
{
    use DatabaseTransactions;

    private function payload(int $zoneId, array $moduleIds, string $name = 'Guard probe'): array
    {
        return [
            'name' => $name,
            'zone_id' => $zoneId,
            'module_ids' => $moduleIds,
            'calculation_method' => EtaConfiguration::METHOD_DISTANCE,
            'minimum_delivery_time' => 10,
            'preparation_buffer' => 5,
            'transit_buffer' => 5,
            'time_gap' => 5,
        ];
    }

    /** A (zone, module) with nothing configured yet. */
    private function freePair(): array
    {
        foreach (DB::table('module_zone')->get(['zone_id', 'module_id']) as $pair) {
            $taken = EtaConfiguration::query()
                ->where('zone_id', $pair->zone_id)
                ->whereHas('modules', fn ($q) => $q->where('modules.id', $pair->module_id))
                ->exists();

            if (! $taken) {
                return [(int) $pair->zone_id, (int) $pair->module_id];
            }
        }

        $this->markTestSkipped('every connected pair already has an ETA configuration');
    }

    public function test_the_service_refuses_a_second_configuration_for_the_same_pair(): void
    {
        [$zoneId, $moduleId] = $this->freePair();
        $service = app(EtaConfigurationService::class);

        $service->create($this->payload($zoneId, [$moduleId], 'First'));

        $this->expectException(DuplicateEtaConfigurationException::class);
        $service->create($this->payload($zoneId, [$moduleId], 'Second'));
    }

    /** The message names the module, so the admin knows which one to drop. */
    public function test_the_refusal_names_the_conflicting_module(): void
    {
        [$zoneId, $moduleId] = $this->freePair();
        $service = app(EtaConfigurationService::class);
        $service->create($this->payload($zoneId, [$moduleId], 'First'));

        $moduleName = DB::table('modules')->where('id', $moduleId)->value('module_name');

        try {
            $service->create($this->payload($zoneId, [$moduleId], 'Second'));
            $this->fail('a duplicate was accepted');
        } catch (DuplicateEtaConfigurationException $e) {
            $this->assertStringContainsString((string) $moduleName, $e->getMessage());
        }
    }

    /** Partial overlap is still a duplicate — only the clashing module is named. */
    public function test_partial_overlap_is_refused(): void
    {
        $pairs = [];

        foreach (DB::table('module_zone')->get(['zone_id', 'module_id']) as $pair) {
            $taken = EtaConfiguration::query()->where('zone_id', $pair->zone_id)
                ->whereHas('modules', fn ($q) => $q->where('modules.id', $pair->module_id))->exists();

            if (! $taken) {
                $pairs[(int) $pair->zone_id][] = (int) $pair->module_id;
            }
        }

        $zoneId = null;
        foreach ($pairs as $zone => $modules) {
            if (count($modules) >= 2) { $zoneId = $zone; break; }
        }

        if (! $zoneId) {
            $this->markTestSkipped('needs a zone with two unconfigured modules');
        }

        [$a, $b] = $pairs[$zoneId];
        $service = app(EtaConfigurationService::class);
        $service->create($this->payload($zoneId, [$a], 'Holds A'));

        $this->expectException(DuplicateEtaConfigurationException::class);
        $service->create($this->payload($zoneId, [$a, $b], 'Wants A and B'));
    }

    /** A different module in the same zone is not a duplicate. */
    public function test_a_different_module_in_the_same_zone_is_allowed(): void
    {
        $pairs = [];

        foreach (DB::table('module_zone')->get(['zone_id', 'module_id']) as $pair) {
            $taken = EtaConfiguration::query()->where('zone_id', $pair->zone_id)
                ->whereHas('modules', fn ($q) => $q->where('modules.id', $pair->module_id))->exists();

            if (! $taken) {
                $pairs[(int) $pair->zone_id][] = (int) $pair->module_id;
            }
        }

        $zoneId = null;
        foreach ($pairs as $zone => $modules) {
            if (count($modules) >= 2) { $zoneId = $zone; break; }
        }

        if (! $zoneId) {
            $this->markTestSkipped('needs a zone with two unconfigured modules');
        }

        [$a, $b] = $pairs[$zoneId];
        $service = app(EtaConfigurationService::class);
        $service->create($this->payload($zoneId, [$a], 'Holds A'));
        $second = $service->create($this->payload($zoneId, [$b], 'Holds B'));

        $this->assertNotNull($second->id);
    }
}
