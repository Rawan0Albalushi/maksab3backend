<?php

namespace Tests\Unit;

use App\Services\Order\DeliveryChargeService;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\AdditionalDeliveryChargeService;
use App\Services\Zone\ModuleZoneDeliveryOptionService;
use App\Services\Zone\ModuleZoneService;
use App\Traits\Order\POSDeliveryTypeTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * S9 — the additional-charge floor, port doc §8.
 *
 * One question — "what is the lowest a delivery here may be priced at" — asked in three places:
 * the setup screen (§8.1), POS deciding whether to offer the saver options (§8.2), and placement
 * clamping the reduction it applies (§8.4a). These pin that all three get the same answer.
 */
class AdditionalChargeFloorTest extends TestCase
{
    use DatabaseTransactions;

    private DeliveryChargeService $engine;

    private ModuleZoneDeliveryOptionService $options;

    private AdditionalDeliveryChargeService $charges;

    private DeliveryRuleService $rules;

    private int $zoneId;

    private int $moduleId;

    private mixed $pivot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(DeliveryChargeService::class);
        $this->options = app(ModuleZoneDeliveryOptionService::class);
        $this->charges = app(AdditionalDeliveryChargeService::class);
        $this->rules = app(DeliveryRuleService::class);

        // A zone that is NOT the default one: D2 refuses to switch off or delete the default
        // zone's last rule, which would make the fallback half of these tests unprovable.
        $row = \DB::table('module_zone')->where('zone_id', '!=', 1)->first()
            ?? \DB::table('module_zone')->first();

        if (! $row) {
            $this->markTestSkipped('needs a module connected to a zone');
        }

        $this->zoneId = (int) $row->zone_id;
        $this->moduleId = (int) $row->module_id;
        $this->pivot = app(ModuleZoneService::class)->findForModuleAndZone($this->moduleId, $this->zoneId);
    }

    /**
     * Switch off every active rule for the pair.
     *
     * S16's backfill gave EVERY connected (zone, module) an active rule, so "a pair with no rule"
     * is no longer a state the world supplies — these tests used to rely on it being the default
     * and started failing the moment the backfill ran. Creating the condition explicitly is what
     * they should have done from the start.
     */
    private function unruleThePair(): void
    {
        \DB::table('delivery_rules')
            ->join('delivery_rule_module', 'delivery_rules.id', '=', 'delivery_rule_module.delivery_rule_id')
            ->where('delivery_rules.zone_id', $this->zoneId)
            ->where('delivery_rule_module.module_id', $this->moduleId)
            ->update(['delivery_rules.status' => 0]);
    }

    private function makeRule(float $minimum, bool $active = true): mixed
    {
        return $this->rules->create([
            'name' => 'Floor probe',
            'zone_id' => $this->zoneId,
            'module_ids' => [$this->moduleId],
            'pricing_method' => 'distance_wise',
            'per_km_charge' => 5,
            'minimum_delivery_charge' => $minimum,
            'maximum_delivery_charge' => 500,
            'status' => $active ? 1 : 0,
        ]);
    }

    // ------------------------------------------------------------------ §8.1, the floor itself

    public function test_an_active_rule_supplies_the_floor(): void
    {
        $this->makeRule(40);

        $this->assertSame(40.0, $this->engine->deliveryFloor($this->zoneId, $this->moduleId, $this->pivot));
    }

    public function test_an_inactive_rule_does_not_supply_the_floor(): void
    {
        $this->unruleThePair();
        $this->makeRule(40, active: false);

        $this->assertSame(
            $this->pivot?->minimum_delivery_charge === null ? null : (float) $this->pivot->minimum_delivery_charge,
            $this->engine->deliveryFloor($this->zoneId, $this->moduleId, $this->pivot),
        );
    }

    public function test_the_pivot_supplies_the_floor_when_no_rule_is_active(): void
    {
        $this->unruleThePair();
        $pivot = (object) ['minimum_delivery_charge' => 12.5];

        $this->assertSame(12.5, $this->engine->deliveryFloor($this->zoneId, $this->moduleId, $pivot));
    }

    public function test_a_pair_with_neither_has_nothing_to_check_against(): void
    {
        $this->unruleThePair();

        // Null rather than 0.0: §8.1 says skip the test, do not reject. Zero would be a floor
        // every reduction clears, which reads the same but means something different.
        $this->assertNull($this->engine->deliveryFloor($this->zoneId, $this->moduleId, (object) ['minimum_delivery_charge' => null]));
    }

    public function test_the_rule_outranks_the_pivot(): void
    {
        $this->makeRule(40);

        $this->assertSame(40.0, $this->engine->deliveryFloor($this->zoneId, $this->moduleId, (object) ['minimum_delivery_charge' => 5]));
    }

    // ------------------------------------------------------------------ §8.1, setup validation

    /**
     * The Additional Charge screen's own payload. Express is well inside its limits so the
     * assertion under test is the reduction, not something firing ahead of it.
     */
    private function saverPayload(float $reduce): array
    {
        return [
            'zone_id' => $this->zoneId,
            'module_ids' => [$this->moduleId],
            'express_extra_charge' => 20,
            'express_reduce_delivery_time' => 10,
            'express_reduce_delivery_time_unit' => 'min',
            'delay_reduce_charge' => $reduce,
            'delay_add_delivery_time' => 10,
            'delay_add_delivery_time_unit' => 'min',
        ];
    }

    public function test_a_reduction_larger_than_the_floor_is_refused(): void
    {
        $this->makeRule(40);

        $errors = $this->charges->setupErrors($this->saverPayload(41), $this->zoneId);

        $this->assertSame('reduce_charge_exceeds_minimum', $errors[$this->moduleId] ?? null);
    }

    public function test_a_reduction_equal_to_the_floor_is_allowed(): void
    {
        // Reducing exactly to the floor lands ON it, which is where §12 says the fee may sit.
        $this->makeRule(40);

        $this->assertSame([], $this->charges->setupErrors($this->saverPayload(40), $this->zoneId));
    }

    public function test_a_pair_with_no_rule_and_no_pivot_floor_is_not_rejected(): void
    {
        $this->unruleThePair();

        // §8.1 — "skip, do not reject".
        if ($this->pivot?->minimum_delivery_charge !== null) {
            $this->markTestSkipped('this pivot carries its own floor');
        }

        $this->assertSame([], $this->charges->setupErrors($this->saverPayload(9999), $this->zoneId));
    }

    public function test_the_floor_test_is_skipped_when_no_zone_is_passed(): void
    {
        // With no zone there is no pair, so there is no floor to test against and the guard
        // stands down rather than guessing which zone was meant.
        $this->unruleThePair();
        $this->makeRule(40);

        $this->assertSame([], $this->charges->setupErrors($this->saverPayload(9999), null));
    }

    // ------------------------------------------------------------------ §8.2, POS

    public function test_pos_hides_the_saver_options_below_the_floor(): void
    {
        $this->makeRule(40);

        if (! $this->pivot || ! (bool) $this->pivot->additional_delivery_option_status) {
            $this->markTestSkipped('the saver options are not switched on for this pair');
        }

        $pos = new class
        {
            use POSDeliveryTypeTrait;
        };

        $this->assertFalse($pos->loadDeliveryTypes($this->moduleId, $this->zoneId, 39.0, false)['enabled']);
        $this->assertSame('below_minimum_charge', $pos->loadDeliveryTypes($this->moduleId, $this->zoneId, 39.0, false)['reason']);
        $this->assertTrue($pos->loadDeliveryTypes($this->moduleId, $this->zoneId, 50.0, false)['enabled']);
    }

    // ------------------------------------------------------------------ §8.4(a), the runtime clamp

    public function test_the_reduction_is_clamped_so_the_fee_never_falls_below_the_floor(): void
    {
        $this->makeRule(40);
        $floor = $this->engine->deliveryFloor($this->zoneId, $this->moduleId, $this->pivot);

        // The arithmetic PlaceNewOrderTrait::resolveSaverDeliveryType() applies.
        $clamp = fn (float $fee, float $reduce) => (float) min($reduce, max(0, $fee - $floor));

        $this->assertSame(10.0, $clamp(140, 10));   // room to spare
        $this->assertSame(1.0, $clamp(41, 10));     // clamped to what is left above the floor
        $this->assertSame(0.0, $clamp(40, 10));     // already on the floor
        $this->assertSame(0.0, $clamp(20, 10));     // already under it — never negative
    }
}
