<?php

namespace Tests\Feature;

use App\Models\DeliveryRule;
use App\Services\Order\DeliveryChargeService;
use App\Services\Parcel\DimensionService;
use App\Services\Parcel\WeightService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The two additive parcel tiers, end to end: the lists a checkout offers, and the fee those
 * selections produce.
 *
 * Written against the LIVE catalogue rather than factories, like the delivery-rule tests beside
 * it — the invariant under test is "what the list offers is exactly what the engine charges for",
 * and both halves have to read the same rows for that to mean anything.
 */
class ParcelDeliveryTierApiTest extends TestCase
{
    use DatabaseTransactions;

    private int $zoneId;

    private int $moduleId;

    private DeliveryRule $rule;

    private int $bandId;

    private int $sizeId;

    private mixed $pivot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moduleId = (int) \DB::table('modules')->where('module_type', 'parcel')->where('status', 1)->value('id');

        if (! $this->moduleId) {
            $this->markTestSkipped('needs an active parcel module');
        }

        $this->pivot = \DB::table('module_zone')->where('module_id', $this->moduleId)->first();

        if (! $this->pivot) {
            $this->markTestSkipped('needs a zone connected to the parcel module');
        }

        $this->zoneId = (int) $this->pivot->zone_id;

        $this->bandId = app(WeightService::class)
            ->create(['name' => 'Band '.uniqid(), 'from_weight' => 900, 'to_weight' => 902])->id;
        $this->sizeId = app(DimensionService::class)
            ->create(['name' => 'Size '.uniqid(), 'max_length' => 91, 'max_width' => 91, 'max_height' => 91])->id;

        $this->rule = app(\App\Services\Zone\DeliveryRuleService::class)->create([
            'zone_id' => $this->zoneId,
            'module_ids' => [$this->moduleId],
            'name' => 'Tier test '.uniqid(),
            'pricing_method' => DeliveryRule::METHOD_FIXED,
            'fixed_charge' => 100,
            'minimum_delivery_charge' => 0,
            'status' => 1,
            'weight_charge_status' => 1,
            'dimension_charge_status' => 1,
            'weight_charges' => [$this->bandId => 20],
            'dimension_charges' => [$this->sizeId => 16],
        ]);
    }

    private function quote(array $extra = []): array
    {
        return app(DeliveryChargeService::class)->quote($extra + [
            'order_type' => 'parcel',
            'distance' => 5.0,
            'store' => null,
            'module_zone_pivot' => $this->pivot,
            'zone_id' => $this->zoneId,
            'module_id' => $this->moduleId,
            'surge' => null,
        ]);
    }

    private function setSwitches(bool $weight, bool $dimension): void
    {
        $this->rule->weight_charge_status = $weight;
        $this->rule->dimension_charge_status = $dimension;
        $this->rule->saveQuietly();
    }

    public function test_both_gates_open_lists_the_band_with_its_charge(): void
    {
        $tier = app(WeightService::class)->bandsForZoneModule($this->zoneId, $this->moduleId);

        $this->assertTrue($tier['status']);
        $this->assertSame(20.0, collect($tier['items'])->firstWhere('id', $this->bandId)['charge']);
    }

    public function test_rule_switch_off_returns_an_empty_list(): void
    {
        $this->setSwitches(false, false);

        $this->assertSame(
            ['status' => false, 'items' => []],
            app(WeightService::class)->bandsForZoneModule($this->zoneId, $this->moduleId),
        );
        $this->assertSame(
            ['status' => false, 'items' => []],
            app(DimensionService::class)->sizesForZoneModule($this->zoneId, $this->moduleId),
        );
    }

    public function test_band_deactivated_on_its_own_screen_drops_out_of_the_list(): void
    {
        app(WeightService::class)->updateStatus($this->bandId, 0);

        $tier = app(WeightService::class)->bandsForZoneModule($this->zoneId, $this->moduleId);

        $this->assertTrue($tier['status'], 'the tier is still on — only the band went away');
        $this->assertNull(collect($tier['items'])->firstWhere('id', $this->bandId));
    }

    public function test_no_active_rule_returns_an_empty_list(): void
    {
        $this->rule->status = 0;
        $this->rule->saveQuietly();

        $this->assertSame(
            ['status' => false, 'items' => []],
            app(WeightService::class)->bandsForZoneModule($this->zoneId, $this->moduleId),
        );
    }

    public function test_the_three_tiers_add_to_the_base(): void
    {
        // forceFill: ParcelCategory guards `name`, and the admin service is not what is under
        // test here — only the amount the engine adds for one.
        $category = (new \App\Models\ParcelCategory)->forceFill([
            'name' => 'Tier test '.uniqid(),
            'charge' => 5,
            'status' => 1,
            'module_id' => $this->moduleId,
        ]);
        $category->save();

        $bare = $this->quote()['delivery_charge'];

        $this->assertSame($bare + 20, $this->quote(['weight_id' => $this->bandId])['delivery_charge']);
        $this->assertSame($bare + 16, $this->quote(['dimension_id' => $this->sizeId])['delivery_charge']);
        $this->assertSame($bare + 5, $this->quote(['parcel_category_id' => $category->id])['delivery_charge']);

        $this->assertSame($bare + 41, $this->quote([
            'parcel_category_id' => $category->id,
            'weight_id' => $this->bandId,
            'dimension_id' => $this->sizeId,
        ])['delivery_charge'], 'category + weight + dimension all stack on the rule base');
    }

    public function test_an_id_posted_at_a_switched_off_tier_costs_nothing(): void
    {
        $this->setSwitches(false, false);

        $this->assertSame(
            $this->quote()['delivery_charge'],
            $this->quote(['weight_id' => $this->bandId, 'dimension_id' => $this->sizeId])['delivery_charge'],
            'a client keeping an id it fetched earlier cannot charge for a disabled tier',
        );
    }

    /**
     * The regression this suite exists for: `getDeliveryCharge()` threw the parcel quote away and
     * returned the null it was handed, so every parcel order was quoted and stored at 0.00.
     */
    public function test_placement_returns_the_quoted_parcel_charge_rather_than_zero(): void
    {
        $trait = new class
        {
            use \App\Traits\Order\PlaceNewOrderTrait;

            public function call($request, $zone, $store, $pivot, $preset, $moduleId)
            {
                return $this->getDeliveryCharge($request, $zone, $store, $pivot, $preset, $moduleId);
            }
        };

        $request = new \Illuminate\Http\Request;
        $request->merge([
            'order_type' => 'parcel',
            'distance' => 5,
            'weight_id' => $this->bandId,
            'dimension_id' => $this->sizeId,
        ]);

        $zone = \App\Models\Zone::find($this->zoneId);
        $moduleRow = $zone->modules()->where('modules.id', $this->moduleId)->first();

        $fee = $trait->call($request, $zone, null, $moduleRow, null, $this->moduleId);

        $this->assertGreaterThan(0, (float) $fee['delivery_charge']);
        $this->assertSame(
            (float) $this->quote(['weight_id' => $this->bandId, 'dimension_id' => $this->sizeId])['delivery_charge'],
            (float) $fee['delivery_charge'],
        );
    }
}
