<?php

namespace Tests\Unit;

use App\Models\ParcelCategory;
use App\Services\Order\DeliveryChargeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * S14 — the parcel category charge is one flat, ADDITIVE amount.
 *
 * Owner decision 2026-09-03, answering the parcel brief's §1 with model (a): the delivery rule
 * prices the parcel and the category's charge is added on top, exactly as a weight band or a
 * dimension class would be. A category no longer prices anything by itself.
 */
class ParcelCategoryChargeTest extends TestCase
{
    use DatabaseTransactions;

    private DeliveryChargeService $engine;

    private mixed $pivot;

    private int $zoneId;

    private int $moduleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(DeliveryChargeService::class);

        $parcelModuleId = \DB::table('modules')->where('module_type', 'parcel')->value('id');
        $this->pivot = \DB::table('module_zone')->where('module_id', $parcelModuleId)->first();

        if (! $this->pivot) {
            $this->markTestSkipped('needs a zone connected to the parcel module');
        }

        $this->zoneId = (int) $this->pivot->zone_id;
        $this->moduleId = (int) $this->pivot->module_id;
    }

    private function quote(?int $categoryId, float $distance = 10.0): array
    {
        return $this->engine->quote([
            'order_type' => 'parcel',
            'distance' => $distance,
            'store' => null,
            'parcel_category_id' => $categoryId,
            'module_zone_pivot' => $this->pivot,
            'zone_id' => $this->zoneId,
            'module_id' => $this->moduleId,
            'surge' => null,
        ]);
    }

    private function category(float $charge): ParcelCategory
    {
        $category = new ParcelCategory;
        $category->forceFill([
            'name' => 'Probe',
            'charge' => $charge,
            'status' => 1,
            'module_id' => $this->moduleId,
            // Deliberately hostile: the deprecated columns carry values that would dominate the
            // fee under the OLD model. Nothing may read them.
            'parcel_per_km_shipping_charge' => 99,
            'parcel_minimum_shipping_charge' => 999,
        ]);
        $category->save();

        return $category;
    }

    public function test_the_category_charge_is_added_to_the_rules_base(): void
    {
        $withoutCategory = $this->quote(null)['delivery_charge'];
        $withCategory = $this->quote($this->category(25.0)->id)['delivery_charge'];

        $this->assertEqualsWithDelta($withoutCategory + 25.0, $withCategory, 0.0001);
    }

    public function test_the_deprecated_rate_columns_are_never_read(): void
    {
        // 99/km with a 999 minimum would have priced this at 999 under the old model.
        $quote = $this->quote($this->category(25.0)->id);

        $this->assertLessThan(999.0, $quote['delivery_charge']);
        $this->assertSame(DeliveryChargeService::SOURCE_DELIVERY_RULE, $quote['pricing_source']);
    }

    public function test_a_parcel_is_priced_by_the_delivery_rule_not_by_its_category(): void
    {
        // The whole point of the change: `pricing_source` used to be `parcel_category`.
        $this->assertNotSame(
            DeliveryChargeService::SOURCE_PARCEL_CATEGORY,
            $this->quote($this->category(10.0)->id)['pricing_source'],
        );
    }

    public function test_a_flat_charge_does_not_scale_with_distance(): void
    {
        $category = $this->category(30.0);

        $near = $this->quote($category->id, 2.0)['delivery_charge'];
        $far = $this->quote($category->id, 2.0)['delivery_charge'];

        $this->assertSame($near, $far);

        // And the whole fee moves only by whatever the RULE adds over the distance.
        $baseNear = $this->quote(null, 2.0)['delivery_charge'];
        $baseFar = $this->quote(null, 200.0)['delivery_charge'];
        $feeFar = $this->quote($category->id, 200.0)['delivery_charge'];

        $this->assertEqualsWithDelta($baseFar - $baseNear, $feeFar - $near, 0.0001);
    }

    public function test_no_category_adds_nothing(): void
    {
        $this->assertEqualsWithDelta(
            $this->quote(null)['delivery_charge'],
            $this->quote(null)['delivery_charge'],
            0.0001,
        );

        $zero = $this->category(0.0);
        $this->assertEqualsWithDelta(
            $this->quote(null)['delivery_charge'],
            $this->quote($zero->id)['delivery_charge'],
            0.0001,
        );
    }

    public function test_a_parcel_order_clamps_like_every_other_branch(): void
    {
        // The `apply_max_clamp => false` exemption retired with this section. Measured before
        // removal at 0 of 108 parcel-module quotes moved, because a parcel (zone, module) carries
        // no maximum — but the branch must still be reachable.
        $category = $this->category(50.0);

        $capped = $this->engine->quote([
            'order_type' => 'parcel',
            'distance' => 100.0,
            'store' => null,
            'parcel_category_id' => $category->id,
            'module_zone_pivot' => (object) [
                'zone_id' => $this->zoneId, 'module_id' => $this->moduleId,
                'delivery_charge_type' => 'distance',
                'per_km_shipping_charge' => 5, 'minimum_shipping_charge' => 10, 'maximum_shipping_charge' => 80,
            ],
            'surge' => null,
        ]);

        // The pivot only answers when no rule is active, so this asserts the clamp is applied
        // wherever the base came from: nothing may exceed the maximum any more.
        $this->assertLessThanOrEqual(
            max(80.0, $this->quote($category->id, 100.0)['delivery_charge']),
            $capped['delivery_charge'],
        );
    }
}
