<?php

namespace Tests\Unit;

use App\Models\DeliveryRule;
use App\Services\Parcel\DimensionService;
use App\Services\Parcel\WeightService;
use App\Services\System\ModuleService;
use App\Services\Zone\DeliveryRuleDimensionChargeService;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\DeliveryRuleWeightChargeService;
use App\Services\Zone\ModuleZoneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The delivery rule's two ADDITIVE parcel tiers — the wizard's Weight Rules and Dimension Rules
 * steps.
 *
 * Storage and gating only. Nothing here prices an order: the fee engine picks these up in a later
 * section, exactly as `baseCharge()` was built and left unwired in S4.
 */
class DeliveryRuleParcelTierTest extends TestCase
{
    use DatabaseTransactions;

    private DeliveryRuleService $rules;

    private array $parcelModuleIds;

    private int $zoneId;

    private ?int $nonParcelModuleId;

    private int $bandId;

    private int $sizeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules = app(DeliveryRuleService::class);
        $this->parcelModuleIds = app(ModuleService::class)->parcelCapableModuleIds();

        if ($this->parcelModuleIds === []) {
            $this->markTestSkipped('needs an active parcel-capable module');
        }

        $moduleZone = app(ModuleZoneService::class);
        $this->zoneId = 0;

        foreach (\DB::table('zones')->orderBy('id')->pluck('id') as $zoneId) {
            if (array_intersect($moduleZone->connectedModuleIds($zoneId), $this->parcelModuleIds)) {
                $this->zoneId = (int) $zoneId;
                break;
            }
        }

        if (! $this->zoneId) {
            $this->markTestSkipped('needs a zone connected to a parcel module');
        }

        $this->nonParcelModuleId = collect($moduleZone->connectedModuleIds($this->zoneId))
            ->reject(fn ($id) => in_array($id, $this->parcelModuleIds, true))
            ->first();

        $this->bandId = app(WeightService::class)
            ->create(['name' => 'T'.uniqid(), 'from_weight' => 900, 'to_weight' => 902])->id;
        $this->sizeId = app(DimensionService::class)
            ->create(['name' => 'T'.uniqid(), 'max_length' => 9, 'max_width' => 9, 'max_height' => 9])->id;
    }

    private function rule(array $overrides = []): DeliveryRule
    {
        return $this->rules->create($overrides + [
            'zone_id' => $this->zoneId,
            'module_ids' => $this->parcelModuleIds,
            'name' => 'Parcel '.uniqid(),
            'pricing_method' => DeliveryRule::METHOD_FIXED,
            'fixed_charge' => 10,
            'minimum_delivery_charge' => 5,
        ]);
    }

    private function weightRows(DeliveryRule $rule): array
    {
        return app(DeliveryRuleWeightChargeService::class)->keyedByWeight($rule->id);
    }

    private function dimensionRows(DeliveryRule $rule): array
    {
        return app(DeliveryRuleDimensionChargeService::class)->keyedByDimension($rule->id);
    }

    public function test_both_tiers_are_off_by_default(): void
    {
        $rule = $this->rule();

        $this->assertFalse($rule->weight_charge_status, 'a rule that charged by weight the moment parcel was connected would surprise every existing order');
        $this->assertFalse($rule->dimension_charge_status);
    }

    public function test_an_enabled_tier_stores_its_charges(): void
    {
        $rule = $this->rule([
            'weight_charge_status' => true,
            'dimension_charge_status' => true,
            'weight_charges' => [$this->bandId => 3.5],
            'dimension_charges' => [$this->sizeId => 4.25],
        ]);

        $this->assertSame([$this->bandId => 3.5], $this->weightRows($rule));
        $this->assertSame([$this->sizeId => 4.25], $this->dimensionRows($rule));
    }

    /** Otherwise switching the step back on would restore amounts the admin last saw months ago. */
    public function test_switching_a_tier_off_clears_its_rows(): void
    {
        $rule = $this->rule([
            'weight_charge_status' => true,
            'dimension_charge_status' => true,
            'weight_charges' => [$this->bandId => 3.5],
            'dimension_charges' => [$this->sizeId => 4.25],
        ]);

        $this->rules->update($rule->id, [
            'zone_id' => $this->zoneId, 'module_ids' => $this->parcelModuleIds, 'name' => $rule->name,
            'pricing_method' => DeliveryRule::METHOD_FIXED, 'fixed_charge' => 10, 'minimum_delivery_charge' => 5,
            'weight_charge_status' => false,
            'dimension_charge_status' => true,
            'weight_charges' => [$this->bandId => 3.5],
            'dimension_charges' => [$this->sizeId => 4.25],
        ]);

        $this->assertSame([], $this->weightRows($rule), 'the disabled tier keeps no rows');
        $this->assertSame([$this->sizeId => 4.25], $this->dimensionRows($rule), 'the other tier is untouched');
    }

    /** A blank input means "no extra for this band", which the design says to write as 0. */
    public function test_a_blank_charge_is_stored_as_zero_not_skipped(): void
    {
        $rule = $this->rule([
            'weight_charge_status' => true,
            'weight_charges' => [$this->bandId => ''],
        ]);

        $this->assertSame([$this->bandId => 0.0], $this->weightRows($rule));
    }

    public function test_a_negative_charge_is_floored_at_zero(): void
    {
        $rule = $this->rule([
            'weight_charge_status' => true,
            'weight_charges' => [$this->bandId => -5],
        ]);

        $this->assertSame([$this->bandId => 0.0], $this->weightRows($rule), 'an additive tier may only ever add');
    }

    /**
     * The UI hides the two steps without parcel, but hiding is not enforcing — a crafted POST
     * must not switch weight pricing on for a food-only rule.
     */
    public function test_a_rule_without_parcel_cannot_enable_either_tier(): void
    {
        if (! $this->nonParcelModuleId) {
            $this->markTestSkipped('needs a non-parcel module connected to this zone');
        }

        $rule = $this->rule([
            'module_ids' => [$this->nonParcelModuleId],
            'weight_charge_status' => true,
            'dimension_charge_status' => true,
            'weight_charges' => [$this->bandId => 99],
            'dimension_charges' => [$this->sizeId => 99],
        ]);

        $this->assertFalse($rule->weight_charge_status);
        $this->assertFalse($rule->dimension_charge_status);
        $this->assertSame([], $this->weightRows($rule));
        $this->assertSame([], $this->dimensionRows($rule));
    }

    /** A rule that loses the parcel module must not keep charges no screen shows. */
    public function test_disconnecting_parcel_clears_both_tiers(): void
    {
        if (! $this->nonParcelModuleId) {
            $this->markTestSkipped('needs a non-parcel module connected to this zone');
        }

        $rule = $this->rule([
            'weight_charge_status' => true,
            'dimension_charge_status' => true,
            'weight_charges' => [$this->bandId => 3.5],
            'dimension_charges' => [$this->sizeId => 4.25],
        ]);
        $this->assertNotSame([], $this->weightRows($rule));

        $this->rules->update($rule->id, [
            'zone_id' => $this->zoneId, 'module_ids' => [$this->nonParcelModuleId], 'name' => $rule->name,
            'pricing_method' => DeliveryRule::METHOD_FIXED, 'fixed_charge' => 10, 'minimum_delivery_charge' => 5,
            'weight_charge_status' => true,
            'dimension_charge_status' => true,
            'weight_charges' => [$this->bandId => 3.5],
            'dimension_charges' => [$this->sizeId => 4.25],
        ]);

        $this->assertSame([], $this->weightRows($rule));
        $this->assertSame([], $this->dimensionRows($rule));
        $this->assertFalse($rule->refresh()->weight_charge_status);
    }

    public function test_connects_parcel_is_capability_driven(): void
    {
        $this->assertTrue($this->rules->connectsParcel(['module_ids' => $this->parcelModuleIds]));
        $this->assertFalse($this->rules->connectsParcel(['module_ids' => []]));

        if ($this->nonParcelModuleId) {
            $this->assertFalse($this->rules->connectsParcel(['module_ids' => [$this->nonParcelModuleId]]));
            $this->assertTrue(
                $this->rules->connectsParcel(['module_ids' => array_merge([$this->nonParcelModuleId], $this->parcelModuleIds)]),
                'one parcel module among several is still parcel',
            );
        }
    }

    /** The tiers price nothing yet — that is a later section, and this pins it. */
    public function test_the_tiers_do_not_affect_the_base_charge(): void
    {
        $rule = $this->rule([
            'weight_charge_status' => true,
            'weight_charges' => [$this->bandId => 100],
        ]);
        $this->rules->updateStatus($rule->id, 1);

        $this->assertSame(
            10.0,
            $this->rules->baseCharge($this->zoneId, $this->parcelModuleIds[0], 5.0),
            'baseCharge must still return the fixed charge alone until the parcel tier is wired in',
        );
    }
}
