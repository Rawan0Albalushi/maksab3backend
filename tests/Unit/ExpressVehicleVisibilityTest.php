<?php

namespace Tests\Unit;

use App\Models\AdditionalDeliveryCharge;
use App\Models\DMVehicle;
use App\Services\Zone\AdditionalDeliveryChargeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The express vehicle filter — decided 2026-09-08.
 *
 * An Additional Delivery Charge setup may name the vehicle categories allowed to take its EXPRESS
 * orders. The rule, in three parts:
 *
 *   - naming none places no restriction: every deliveryman may take the order;
 *   - naming some hides that pair's express orders from deliverymen riding anything else;
 *   - express is still OFFERED at checkout either way, so an order nobody is eligible for waits
 *     unassigned rather than the option disappearing for the customer.
 *
 * The filter was stored, editable and optional for weeks with no consumer at all — configured and
 * inert. These tests are on the consumer.
 */
class ExpressVehicleVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private function setupWith(array $vehicleIds): AdditionalDeliveryCharge
    {
        $zoneId = (int) DB::table('zones')->value('id');
        $moduleId = (int) DB::table('modules')->value('id');

        $setup = AdditionalDeliveryCharge::create([
            'zone_id' => $zoneId,
            'express_extra_charge' => 10,
            'express_reduce_delivery_time' => 5,
            'delay_reduce_charge' => 0,
            'delay_add_delivery_time' => 0,
            'status' => 1,
        ]);
        $setup->modules()->sync([$moduleId]);
        $setup->vehicles()->sync($vehicleIds);

        return $setup->load(['modules', 'vehicles']);
    }

    private function twoVehicles(): array
    {
        $ids = DMVehicle::query()->orderBy('id')->limit(2)->pluck('id')->all();

        if (count($ids) < 2) {
            $this->markTestSkipped('needs two vehicle categories');
        }

        return $ids;
    }

    public function test_naming_no_vehicle_places_no_restriction(): void
    {
        $this->setupWith([]);

        $this->assertSame(
            [],
            app(AdditionalDeliveryChargeService::class)->pairsBarredForExpress($this->twoVehicles()[0]),
            'an empty vehicle list must let every deliveryman take the express order',
        );
    }

    public function test_a_listed_vehicle_is_not_barred(): void
    {
        [$a] = $this->twoVehicles();
        $this->setupWith([$a]);

        $barred = app(AdditionalDeliveryChargeService::class)->pairsBarredForExpress($a);

        $this->assertSame([], $barred, 'a deliveryman on a listed vehicle must still see the order');
    }

    public function test_an_unlisted_vehicle_is_barred_from_that_pair(): void
    {
        [$a, $b] = $this->twoVehicles();
        $setup = $this->setupWith([$a]);

        $barred = app(AdditionalDeliveryChargeService::class)->pairsBarredForExpress($b);

        $this->assertNotEmpty($barred, 'a deliveryman on an unlisted vehicle must be barred');
        $this->assertContains(
            ['zone_id' => (int) $setup->zone_id, 'module_id' => (int) $setup->modules->first()->id],
            $barred,
        );
    }

    /** A deliveryman with no vehicle recorded cannot satisfy a list that names some. */
    public function test_a_deliveryman_with_no_vehicle_is_barred_when_the_list_names_any(): void
    {
        [$a] = $this->twoVehicles();
        $this->setupWith([$a]);

        $this->assertNotEmpty(app(AdditionalDeliveryChargeService::class)->pairsBarredForExpress(null));
    }

    /** An inactive setup restricts nothing — it is not in force. */
    public function test_an_inactive_setup_places_no_restriction(): void
    {
        [$a, $b] = $this->twoVehicles();
        $setup = $this->setupWith([$a]);
        $setup->update(['status' => 0]);

        $this->assertSame([], app(AdditionalDeliveryChargeService::class)->pairsBarredForExpress($b));
    }
}
