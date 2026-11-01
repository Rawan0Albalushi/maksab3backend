<?php

namespace Tests\Unit;

use App\Models\AdditionalDeliveryCharge;
use App\Models\ModuleZoneDeliveryOption;
use App\Services\Zone\AdditionalDeliveryChargeService;
use App\Services\Zone\ModuleZoneDeliveryOptionService;
use App\Services\Zone\ModuleZoneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S19 — Additional Delivery Charge, per (zone, module).
 *
 * The express and slightly-delayed offers used to be edited inline on Module Setup and stored in
 * `module_zone_delivery_options`. They now have their own screen and their own tables. These pin
 * the two halves that matter: the setup rules the design states, and the read seam the order
 * paths still reach through — because storage moved underneath them without their knowing.
 */
class AdditionalDeliveryChargeTest extends TestCase
{
    use DatabaseTransactions;

    private AdditionalDeliveryChargeService $service;

    private ModuleZoneDeliveryOptionService $options;

    private int $zoneId;

    private int $moduleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AdditionalDeliveryChargeService::class);
        $this->options = app(ModuleZoneDeliveryOptionService::class);
        $this->service->forgetActive();

        // A pair the platform actually connects, so the connected-modules guard is not what the
        // assertions end up measuring.
        foreach (DB::table('zones')->orderBy('id')->pluck('id') as $zoneId) {
            $connected = app(ModuleZoneService::class)->connectedModuleIds($zoneId);

            if ($connected !== []) {
                $this->zoneId = (int) $zoneId;
                $this->moduleId = (int) $connected[0];
                break;
            }
        }

        if (! isset($this->zoneId)) {
            $this->markTestSkipped('no zone on this install has a connected module');
        }

        // Start from nothing so a setup left by the data migration cannot collide.
        AdditionalDeliveryCharge::where('zone_id', $this->zoneId)->each(fn ($s) => $this->service->delete($s->id));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'zone_id' => $this->zoneId,
            'module_ids' => [$this->moduleId],
            'vehicle_ids' => [],
            'express_extra_charge' => 5,
            'express_reduce_delivery_time' => 10,
            'express_reduce_delivery_time_unit' => 'min',
            'delay_reduce_charge' => 3,
            'delay_add_delivery_time' => 15,
            'delay_add_delivery_time_unit' => 'min',
        ], $overrides);
    }

    // ------------------------------------------------------------------------------ storage

    public function test_a_setup_stores_both_offers_and_its_modules(): void
    {
        $setup = $this->service->create($this->payload());

        $this->assertSame(5.0, $setup->express_extra_charge);
        $this->assertSame(10, $setup->express_reduce_delivery_time);
        $this->assertSame(3.0, $setup->delay_reduce_charge);
        $this->assertSame(15, $setup->delay_add_delivery_time);
        $this->assertSame([$this->moduleId], $setup->modules->pluck('id')->all());
    }

    public function test_an_hour_is_multiplied_out_to_minutes_before_it_is_stored(): void
    {
        // Nothing downstream carries a unit beside the number, so the unit has to be spent here.
        $setup = $this->service->create($this->payload([
            'express_reduce_delivery_time' => 2,
            'express_reduce_delivery_time_unit' => 'hour',
        ]));

        $this->assertSame(120, $setup->express_reduce_delivery_time);
    }

    public function test_exact_hours_round_trip_back_to_the_form_as_hours(): void
    {
        $this->assertSame(['value' => 2, 'unit' => 'hour'], AdditionalDeliveryCharge::minutesToPair(120));
        // 90 minutes is not a whole number of hours, so collapsing it would not survive the trip
        // back through an integer field.
        $this->assertSame(['value' => 90, 'unit' => 'min'], AdditionalDeliveryCharge::minutesToPair(90));
    }

    public function test_the_express_vehicle_filter_is_saved_and_is_optional(): void
    {
        $vehicleId = DB::table('d_m_vehicles')->value('id');

        if (! $vehicleId) {
            $this->markTestSkipped('this install has no delivery vehicle categories');
        }

        $setup = $this->service->create($this->payload(['vehicle_ids' => [$vehicleId]]));
        $this->assertSame([(int) $vehicleId], $setup->vehicles->pluck('id')->map('intval')->all());

        $cleared = $this->service->update($setup->id, $this->payload(['vehicle_ids' => []]));
        $this->assertSame([], $cleared->vehicles->pluck('id')->all(), 'empty must mean no filter, not an unchanged one');
    }

    // ------------------------------------------------------- the design's uniqueness side note

    public function test_a_second_setup_claiming_the_same_module_is_refused_by_name(): void
    {
        $this->service->create($this->payload());

        $clashing = $this->service->conflictingModuleNames($this->zoneId, [$this->moduleId]);

        $this->assertNotSame([], $clashing, 'one setup per zone and module combination');
    }

    public function test_a_setup_editing_itself_may_keep_its_own_modules(): void
    {
        $setup = $this->service->create($this->payload());

        $this->assertSame([], $this->service->conflictingModuleNames($this->zoneId, [$this->moduleId], $setup->id));
    }

    public function test_the_picker_offers_only_modules_not_already_taken(): void
    {
        $before = $this->service->modulePickerForZone($this->zoneId)['modules']->pluck('id')->all();
        $this->assertContains($this->moduleId, $before);

        $this->service->create($this->payload());

        $after = $this->service->modulePickerForZone($this->zoneId)['modules']->pluck('id')->all();
        $this->assertNotContains($this->moduleId, $after);
    }

    // --------------------------------------------------------------------- both halves or none

    public function test_an_offer_with_a_charge_but_no_time_is_refused(): void
    {
        $errors = $this->service->setupErrors($this->payload(['express_reduce_delivery_time' => 0]), $this->zoneId);

        $this->assertSame('express_required', $errors[$this->moduleId] ?? null);
    }

    public function test_an_offer_with_time_but_no_charge_is_refused(): void
    {
        $errors = $this->service->setupErrors($this->payload(['delay_reduce_charge' => 0]), $this->zoneId);

        $this->assertSame('slightly_delay_required', $errors[$this->moduleId] ?? null);
    }

    // ------------------------------------------------- the read seam the order paths still use

    public function test_the_order_paths_read_the_new_setup_through_the_old_seam(): void
    {
        $this->service->create($this->payload());

        $express = $this->options->findForModuleZoneType($this->moduleId, $this->zoneId, ModuleZoneDeliveryOption::TYPE_EXPRESS);
        $delay = $this->options->findForModuleZoneType($this->moduleId, $this->zoneId, ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY);

        $this->assertSame(5.0, (float) $express->extra_charge);
        $this->assertSame(10, (int) $express->getRawOriginal('reduce_delivery_time'));
        $this->assertSame(3.0, (float) $delay->reduce_charge);
        $this->assertSame(15, (int) $delay->getRawOriginal('add_delivery_time'));
    }

    public function test_the_options_list_leads_with_standard(): void
    {
        $this->service->create($this->payload());

        $this->assertSame(
            ['standard', 'express', 'slightly_delay'],
            $this->options->optionsFor($this->moduleId, $this->zoneId)->pluck('delivery_type')->all(),
        );
    }

    public function test_an_offer_id_round_trips_back_to_its_type(): void
    {
        // The POS renders the list, then posts back the id of the offer the customer picked.
        $this->service->create($this->payload());

        foreach ($this->options->optionsFor($this->moduleId, $this->zoneId) as $option) {
            if ($option->delivery_type === 'standard') {
                continue;
            }

            $this->assertSame($option->delivery_type, $this->options->findDeliveryType($option->id));
        }
    }

    public function test_a_pair_with_no_setup_offers_nothing_rather_than_a_default(): void
    {
        // An express charge nobody configured is money taken for a promise never made.
        $this->assertSame([], $this->options->optionsFor($this->moduleId, $this->zoneId)->all());
        $this->assertNull($this->options->findForModuleZoneType($this->moduleId, $this->zoneId, 'express'));
    }

    public function test_a_switched_off_setup_makes_no_offer(): void
    {
        $setup = $this->service->create($this->payload());
        $this->service->updateStatus($setup->id, 0);

        $this->assertNull($this->service->activeSetup($this->zoneId, $this->moduleId));
    }

    public function test_resolving_a_setup_for_a_page_costs_one_query_not_one_per_row(): void
    {
        $this->service->create($this->payload());
        $this->service->forgetActive();

        DB::enableQueryLog();
        DB::flushQueryLog();

        for ($i = 0; $i < 5; $i++) {
            $this->service->activeSetup($this->zoneId, $this->moduleId);
        }

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $count, 'five rows asking the same question must not issue five queries');
    }
}
