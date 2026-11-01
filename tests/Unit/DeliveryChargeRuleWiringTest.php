<?php

namespace Tests\Unit;

use App\Models\DeliveryRule;
use App\Services\Order\DeliveryChargeService;
use App\Services\Zone\AreaService;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\ZipCodeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * S5 — the delivery rule as step 2b of the fee engine.
 *
 * §12's order is: 2a store self-delivery, 2b active rule, 2c module_zone pivot. These tests pin
 * that precedence and each pricing method's arithmetic through `quote()` itself, not through
 * `baseCharge()` — the point of S5 is that the engine consults the rule, and testing the rule
 * service alone would pass even if the wiring were absent.
 */
class DeliveryChargeRuleWiringTest extends TestCase
{
    use DatabaseTransactions;

    private DeliveryChargeService $engine;

    private DeliveryRuleService $rules;

    private int $zoneId;

    private int $moduleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(DeliveryChargeService::class);
        $this->rules = app(DeliveryRuleService::class);
        $this->zoneId = (int) \DB::table('zones')->value('id');
        $this->moduleId = (int) \DB::table('modules')->value('id');
    }

    private function activate(array $overrides): DeliveryRule
    {
        $rule = $this->rules->create($overrides + [
            'zone_id' => $this->zoneId,
            'module_ids' => [$this->moduleId],
            'name' => 'Wiring '.uniqid(),
            'minimum_delivery_charge' => 0,
        ]);
        $this->rules->updateStatus($rule->id, 1);

        return $rule->refresh();
    }

    private function quote(array $context = []): array
    {
        return $this->engine->quote($context + [
            'order_type' => 'delivery',
            'distance' => 5.0,
            'zone_id' => $this->zoneId,
            'module_id' => $this->moduleId,
        ]);
    }

    /** A pivot object shaped like the real one, so 2c has something to answer with. */
    private function pivot(array $values = []): object
    {
        return (object) ($values + [
            'zone_id' => $this->zoneId,
            'module_id' => $this->moduleId,
            'delivery_charge_type' => 'distance',
            'per_km_shipping_charge' => 100.0,
            'minimum_shipping_charge' => 0.0,
            'maximum_shipping_charge' => 1000.0,
        ]);
    }

    public function test_without_a_rule_the_pivot_still_prices_it(): void
    {
        $quote = $this->quote(['module_zone_pivot' => $this->pivot()]);

        $this->assertSame(DeliveryChargeService::SOURCE_MODULE_ZONE, $quote['pricing_source']);
        $this->assertSame(500.0, $quote['base_delivery_charge'], '5 km x 100 — unchanged by S5');
    }

    /** An INACTIVE rule must not price anything; only the active one is step 2b. */
    public function test_an_inactive_rule_is_ignored(): void
    {
        // 'status' => false explicitly: the FIRST rule for a (zone, module) is created active
        // now, so leaving it to the default would have made this an active-rule test wearing an
        // inactive-rule name.
        $this->rules->create([
            'zone_id' => $this->zoneId, 'module_ids' => [$this->moduleId], 'name' => 'Off '.uniqid(),
            'pricing_method' => DeliveryRule::METHOD_FIXED, 'fixed_charge' => 7, 'minimum_delivery_charge' => 0,
            'status' => false,
        ]);

        $quote = $this->quote(['module_zone_pivot' => $this->pivot()]);

        $this->assertSame(DeliveryChargeService::SOURCE_MODULE_ZONE, $quote['pricing_source']);
        $this->assertSame(500.0, $quote['base_delivery_charge']);
    }

    public function test_an_active_rule_outranks_the_pivot(): void
    {
        $this->activate(['pricing_method' => DeliveryRule::METHOD_FIXED, 'fixed_charge' => 42]);

        $quote = $this->quote(['module_zone_pivot' => $this->pivot()]);

        $this->assertSame(DeliveryChargeService::SOURCE_DELIVERY_RULE, $quote['pricing_source']);
        $this->assertSame(42.0, $quote['base_delivery_charge']);
    }

    /** 2a beats 2b: a self-delivery store sets its own rates and no rule overrides them. */
    public function test_a_self_delivery_store_still_outranks_a_rule(): void
    {
        $this->activate(['pricing_method' => DeliveryRule::METHOD_FIXED, 'fixed_charge' => 42]);

        $quote = $this->quote([
            'module_zone_pivot' => $this->pivot(),
            'store' => (object) [
                'sub_self_delivery' => 1,
                'per_km_shipping_charge' => 3.0,
                'minimum_shipping_charge' => 0.0,
                'maximum_shipping_charge' => 1000.0,
            ],
        ]);

        $this->assertSame(DeliveryChargeService::SOURCE_SELF_DELIVERY, $quote['pricing_source']);
        $this->assertSame(15.0, $quote['base_delivery_charge'], '5 km x 3 — the store\'s own rate');
    }

    /** A fixed amount must not be multiplied by the distance, whatever the journey. */
    public function test_a_fixed_rule_ignores_the_distance(): void
    {
        $this->activate(['pricing_method' => DeliveryRule::METHOD_FIXED, 'fixed_charge' => 20]);

        foreach ([0.0, 5.0, 250.0] as $km) {
            $this->assertSame(20.0, $this->quote(['distance' => $km])['base_delivery_charge'], "at {$km} km");
        }
    }

    public function test_a_fixed_rule_is_lifted_to_its_own_minimum(): void
    {
        $this->activate([
            'pricing_method' => DeliveryRule::METHOD_FIXED, 'fixed_charge' => 3, 'minimum_delivery_charge' => 12,
        ]);

        $this->assertSame(12.0, $this->quote()['base_delivery_charge']);
    }

    public function test_a_distance_rule_multiplies_clamps_and_floors(): void
    {
        $this->activate([
            'pricing_method' => DeliveryRule::METHOD_DISTANCE,
            'per_km_charge' => 2,
            'maximum_delivery_charge' => 100,
            'minimum_delivery_charge' => 10,
        ]);

        $this->assertSame(30.0, $this->quote(['distance' => 15.0])['base_delivery_charge'], 'plain multiplication');
        $this->assertSame(100.0, $this->quote(['distance' => 200.0])['base_delivery_charge'], 'clamped to the maximum');
        $this->assertSame(10.0, $this->quote(['distance' => 0.5])['base_delivery_charge'], 'lifted to the floor');
    }

    public function test_an_area_rule_prices_the_customers_pick(): void
    {
        $area = app(AreaService::class)->create(['zone_id' => $this->zoneId, 'name' => 'W '.uniqid()]);
        $this->activate([
            'pricing_method' => DeliveryRule::METHOD_AREA,
            'minimum_delivery_charge' => 5,
            'charges' => [$area->id => 15],
        ]);

        $this->assertSame(15.0, $this->quote(['area_id' => $area->id])['base_delivery_charge']);
        // §5.3 — an unpriced pick is 0, then the rule's floor lifts it rather than shipping free.
        $this->assertSame(5.0, $this->quote(['area_id' => 99999999])['base_delivery_charge']);
        $this->assertSame(5.0, $this->quote()['base_delivery_charge'], 'no pick at all still costs the floor');
    }

    public function test_a_zip_rule_prices_the_customers_pick(): void
    {
        $zip = app(ZipCodeService::class)->create(['zone_id' => $this->zoneId, 'zip_code' => 'W-'.uniqid()]);
        $this->activate([
            'pricing_method' => DeliveryRule::METHOD_ZIP,
            'minimum_delivery_charge' => 5,
            'charges' => [$zip->id => 25],
        ]);

        $this->assertSame(25.0, $this->quote(['zip_code_id' => $zip->id])['base_delivery_charge']);
    }

    /** The floor the post-engine discount must respect (M7) comes from whichever branch ran. */
    public function test_the_quote_reports_the_rules_minimum_as_the_floor(): void
    {
        $this->activate([
            'pricing_method' => DeliveryRule::METHOD_FIXED, 'fixed_charge' => 40, 'minimum_delivery_charge' => 18,
        ]);

        $this->assertSame(18.0, $this->quote()['floor']);
    }

    /** A caller that only passes the pivot must still reach the rule — no call-site change. */
    public function test_zone_and_module_fall_back_to_the_pivots_own_columns(): void
    {
        $this->activate(['pricing_method' => DeliveryRule::METHOD_FIXED, 'fixed_charge' => 33]);

        $quote = $this->engine->quote([
            'order_type' => 'delivery',
            'distance' => 5.0,
            'module_zone_pivot' => $this->pivot(),
        ]);

        $this->assertSame(DeliveryChargeService::SOURCE_DELIVERY_RULE, $quote['pricing_source']);
        $this->assertSame(33.0, $quote['base_delivery_charge']);
    }

    /** Take-away never has a delivery charge, rule or no rule. */
    public function test_take_away_is_untouched_by_a_rule(): void
    {
        $this->activate(['pricing_method' => DeliveryRule::METHOD_FIXED, 'fixed_charge' => 99]);

        $this->assertSame(0.0, $this->quote(['order_type' => 'take_away'])['delivery_charge']);
    }

    /**
     * §5.4 — a pick from ANOTHER zone must never price the order.
     *
     * The callers refuse it outright with a 403; this pins the engine's own second line, because
     * an exploit that reaches the engine must still not buy a cheaper area's rate.
     */
    public function test_a_coverage_pick_from_another_zone_is_not_priced(): void
    {
        $otherZoneId = (int) \DB::table('zones')->where('id', '!=', $this->zoneId)->value('id');

        if (! $otherZoneId) {
            $this->markTestSkipped('needs a second zone');
        }

        $foreign = app(AreaService::class)->create(['zone_id' => $otherZoneId, 'name' => 'Foreign '.uniqid()]);
        $mine = app(AreaService::class)->create(['zone_id' => $this->zoneId, 'name' => 'Mine '.uniqid()]);

        $this->activate([
            'pricing_method' => DeliveryRule::METHOD_AREA,
            'minimum_delivery_charge' => 40,
            'charges' => [$mine->id => 90],
        ]);

        // The foreign area is cheap in its own zone; posting it here must not buy that price.
        $this->assertSame(
            40.0,
            $this->quote(['area_id' => $foreign->id])['base_delivery_charge'],
            'a foreign pick falls back to the floor, never to another zone\'s rate',
        );
        $this->assertSame(90.0, $this->quote(['area_id' => $mine->id])['base_delivery_charge']);
    }
}
