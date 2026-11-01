<?php

namespace Tests\Unit;

use App\Models\EtaConfiguration;
use App\Models\Module;
use App\Services\Zone\EtaConfigurationService;
use App\Services\Zone\ModuleZoneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * S7 — ETA configuration per (zone, module).
 *
 * The arithmetic tests here pin §11.2's one counter-intuitive rule: `minimum_delivery_time` is a
 * FLOOR, not a term. Read as a term it would silently add minutes to every estimate; read as a
 * floor it only ever lifts one that came in short. Both directions are asserted, because a wrong
 * reading passes half of them.
 */
class EtaConfigurationTest extends TestCase
{
    use DatabaseTransactions;

    private EtaConfigurationService $eta;

    private int $zoneId;

    private int $moduleId;

    private int $secondModuleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eta = app(EtaConfigurationService::class);

        $zones = \DB::table('module_zone')
            ->select('zone_id')
            ->groupBy('zone_id')
            ->havingRaw('count(*) >= 2')
            ->pluck('zone_id');

        if ($zones->isEmpty()) {
            $this->markTestSkipped('needs a zone connected to at least two modules');
        }

        $this->zoneId = (int) $zones->first();
        $connected = app(ModuleZoneService::class)->connectedModuleIds($this->zoneId);
        $this->moduleId = (int) $connected[0];
        $this->secondModuleId = (int) $connected[1];
    }

    private function make(array $overrides = []): EtaConfiguration
    {
        return $this->eta->create(array_merge([
            'name' => 'Test ETA',
            'zone_id' => $this->zoneId,
            'module_ids' => [$this->moduleId],
            'calculation_method' => EtaConfiguration::METHOD_DISTANCE,
            'minimum_delivery_time' => 20,
            'preparation_buffer' => 10,
            'transit_buffer' => 5,
            'time_gap' => 15,
        ], $overrides));
    }

    // ------------------------------------------------------------------ §11.2, the floor

    public function test_the_minimum_delivery_time_lifts_an_estimate_that_came_in_short(): void
    {
        // buffers 10 + 5 = 15, under the floor of 20
        $this->assertSame(20, $this->make()->minimumEtaMinutes());
    }

    public function test_the_minimum_delivery_time_is_not_added_to_an_estimate_that_already_clears_it(): void
    {
        // buffers 30 + 10 = 40, over the floor of 5 — a term would give 45
        $config = $this->make(['minimum_delivery_time' => 5, 'preparation_buffer' => 30, 'transit_buffer' => 10]);

        $this->assertSame(40, $config->minimumEtaMinutes());
    }

    public function test_the_gap_widens_the_floor_rather_than_the_buffers(): void
    {
        $config = $this->make();

        $this->assertSame(20, $config->minimumEtaMinutes());
        $this->assertSame(35, $config->maximumEtaMinutes());
        $this->assertStringContainsString('20', $config->etaRangeLabel());
        $this->assertStringContainsString('35', $config->etaRangeLabel());
    }

    // ------------------------------------------------------------------ the gap is distance-only

    public function test_the_fixed_method_keeps_no_gap_and_names_no_maximum(): void
    {
        $config = $this->make(['calculation_method' => EtaConfiguration::METHOD_FIXED]);

        $this->assertNull($config->time_gap);
        $this->assertNull($config->maximumEtaMinutes());
        $this->assertFalse($config->usesTimeGap());
    }

    public function test_switching_to_the_fixed_method_clears_a_gap_that_was_already_set(): void
    {
        $config = $this->make();
        $this->assertSame(15, $config->time_gap);

        $updated = $this->eta->update($config->id, [
            'zone_id' => $this->zoneId,
            'module_ids' => [$this->moduleId],
            'calculation_method' => EtaConfiguration::METHOD_FIXED,
            'minimum_delivery_time' => 20,
            'preparation_buffer' => 10,
            'transit_buffer' => 5,
            'time_gap' => 15,
        ]);

        $this->assertNull($updated->time_gap);
    }

    // ------------------------------------------------------------------ §11.2, estimate nothing

    public function test_a_module_with_no_configuration_resolves_to_null(): void
    {
        $this->make();

        $this->assertNull($this->eta->activeConfig([$this->zoneId], $this->secondModuleId));
    }

    public function test_a_configuration_that_is_switched_off_resolves_to_null(): void
    {
        $config = $this->make();
        $this->eta->updateStatus($config->id, 0);

        $this->assertNull($this->eta->activeConfig([$this->zoneId], $this->moduleId));
    }

    public function test_an_active_configuration_resolves_for_its_own_zone_and_module(): void
    {
        $config = $this->make();

        $this->assertSame($config->id, $this->eta->activeConfig([$this->zoneId], $this->moduleId)?->id);
    }

    public function test_overlapping_zones_are_walked_in_the_order_given(): void
    {
        $config = $this->make();

        // A zone with no configuration ahead of one that has it must not short-circuit the walk.
        $this->assertSame($config->id, $this->eta->activeConfig([999999, $this->zoneId], $this->moduleId)?->id);
    }

    // ------------------------------------------------------------------ E1 and D7

    public function test_a_second_configuration_cannot_claim_a_module_the_first_already_covers(): void
    {
        $this->make();

        $clashes = $this->eta->conflictingModuleNames($this->zoneId, [$this->moduleId, $this->secondModuleId]);

        $this->assertContains(Module::find($this->moduleId)->module_name, $clashes);
        $this->assertNotContains(Module::find($this->secondModuleId)->module_name, $clashes);
    }

    public function test_a_configuration_does_not_clash_with_itself_while_being_edited(): void
    {
        $config = $this->make();

        $this->assertSame([], $this->eta->conflictingModuleNames($this->zoneId, [$this->moduleId], $config->id));
    }

    public function test_the_picker_drops_a_module_another_configuration_already_covers(): void
    {
        $this->make();

        $available = $this->eta->modulePickerForZone($this->zoneId)['modules']->pluck('id')->all();

        $this->assertNotContains($this->moduleId, $available);
        $this->assertContains($this->secondModuleId, $available);
    }

    public function test_the_picker_offers_a_configurations_own_modules_back_while_it_is_edited(): void
    {
        $config = $this->make();

        $available = $this->eta->modulePickerForZone($this->zoneId, $config->id)['modules']->pluck('id')->all();

        $this->assertContains($this->moduleId, $available);
    }

    public function test_a_module_the_zone_is_not_connected_to_is_named_and_refused(): void
    {
        $connected = app(ModuleZoneService::class)->connectedModuleIds($this->zoneId);
        $stranger = Module::whereNotIn('id', $connected)->first();

        if (! $stranger) {
            $this->markTestSkipped('every module is connected to this zone');
        }

        $this->assertSame(
            [$stranger->module_name],
            $this->eta->unconnectedModuleNames($this->zoneId, [$stranger->id]),
        );
    }

    // ------------------------------------------------------------------ housekeeping

    public function test_deleting_a_configuration_takes_its_pivot_rows_with_it(): void
    {
        $config = $this->make();

        $this->assertTrue($this->eta->delete($config->id));
        $this->assertNull(EtaConfiguration::find($config->id));
        $this->assertSame(0, \DB::table('eta_configuration_module')
            ->where('eta_configuration_id', $config->id)->count());
    }
}
