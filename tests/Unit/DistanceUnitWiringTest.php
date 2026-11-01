<?php

namespace Tests\Unit;

use App\CentralLogics\Helpers;
use App\Models\Store;
use App\Services\DeliveryMan\DmVehicleService;
use App\Services\Order\DeliveryChargeService;
use App\Services\System\DistanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * S10 — the distance unit reaching the two places it is allowed to (port doc §3.2, §3.4).
 *
 * S2 built the kernel and deliberately wired nothing; this is the phase the S2 report's
 * "test 8 — setup values re-read rather than converted" was waiting for.
 *
 * The model these pin: a MEASURED distance is kilometres always and is converted at the
 * boundary; a SETUP value — a rate, a coverage band — is stored exactly as typed and is never
 * converted, only re-read in whatever unit the setting names.
 */
class DistanceUnitWiringTest extends TestCase
{
    use DatabaseTransactions;

    private DistanceService $distance;

    private DeliveryChargeService $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->distance = app(DistanceService::class);
        $this->engine = app(DeliveryChargeService::class);
    }

    private function useUnit(string $unit): void
    {
        \DB::table('business_settings')->updateOrInsert(['key' => 'distance_unit'], ['value' => $unit]);
        Helpers::clearBusinessSettingsCache();
        $this->distance->forgetUnit();
    }

    // ------------------------------------------------------------------ meeting point 1: the rate

    public function test_a_rate_multiplication_converts_the_distance_first(): void
    {
        $this->useUnit('km');
        $inKm = $this->engine->chargeableBase(10.0, perUnit: 4.0, minimum: 0.0);

        $this->useUnit('mi');
        $inMi = $this->engine->chargeableBase(10.0, perUnit: 4.0, minimum: 0.0);

        // 10 km × 4 = 40. The same 10 km is 6.2137 mi, so × 4 = 24.85.
        $this->assertSame(40.0, $inKm);
        $this->assertEqualsWithDelta(24.85, $inMi, 0.01);
    }

    public function test_the_rate_itself_is_never_converted(): void
    {
        // A SETUP value: 4 stays 4 and simply means "per mile" now. Proved by the arithmetic —
        // a converted rate would give 10 × 6.437 = 64.37, not 6.2137 × 4.
        $this->useUnit('mi');

        $this->assertEqualsWithDelta(
            $this->distance->chargeable(10.0) * 4.0,
            $this->engine->chargeableBase(10.0, perUnit: 4.0, minimum: 0.0),
            0.0001,
        );
    }

    public function test_the_floor_still_applies_after_conversion(): void
    {
        $this->useUnit('mi');

        // 10 km = 6.2137 mi × 4 = 24.85, under a floor of 50.
        $this->assertSame(50.0, $this->engine->chargeableBase(10.0, perUnit: 4.0, minimum: 50.0));
    }

    public function test_a_flat_base_is_never_multiplied_by_a_distance_in_either_unit(): void
    {
        foreach (['km', 'mi'] as $unit) {
            $this->useUnit($unit);
            $this->assertSame(30.0, $this->engine->chargeableBase(10.0, perUnit: 4.0, minimum: 0.0, flat: 30.0), $unit);
        }
    }

    public function test_the_operand_carries_money_precision_not_display_precision(): void
    {
        $this->useUnit('mi');

        // 2 dp would give 6.21; 4 dp gives 6.2137. On a 4.00 rate that is 24.84 against 24.85 —
        // small per order, and the reason the constant exists.
        $this->assertSame(6.2137, $this->distance->chargeable(10.0));
        $this->assertSame(6.21, $this->distance->convert(10.0));
    }

    // ------------------------------------------------------------------ meeting point 2: the band

    public function test_a_coverage_band_comparison_converts_the_distance_first(): void
    {
        $vehicles = app(DmVehicleService::class);
        $band = \DB::table('d_m_vehicles')->where('status', 1)->first();

        if (! $band) {
            $this->markTestSkipped('needs an active vehicle with a coverage band');
        }

        // A distance just above the band's top in kilometres falls back INSIDE it once read as
        // miles, because the same journey is a smaller number of miles.
        $justOver = ((float) $band->maximum_coverage_area) + 1;

        $this->useUnit('km');
        $inKm = $vehicles->coverageVehicle($justOver);

        $this->useUnit('mi');
        $inMi = $vehicles->coverageVehicle($justOver);

        $this->assertNotSame($inKm?->id, $inMi?->id, 'the band comparison must see a converted distance');
        $this->assertSame((int) $band->id, (int) $inMi?->id);
    }

    // ------------------------------------------------------------------ §3.4 display surfaces

    public function test_the_order_distance_label_follows_the_setting(): void
    {
        $order = new \App\Models\Order;
        $order->distance = 10.0;

        $this->useUnit('km');
        $this->assertSame('10 km', $order->distance_label);

        $this->useUnit('mi');
        $this->assertSame('6.21 mi', $order->distance_label);
    }

    public function test_a_zero_distance_has_no_label(): void
    {
        $order = new \App\Models\Order;
        $order->distance = 0;

        $this->assertNull($order->distance_label);
    }

    public function test_a_store_card_emits_both_units_whatever_the_setting_says(): void
    {
        $store = Store::withoutGlobalScopes()->first();

        if (! $store) {
            $this->markTestSkipped('needs a store');
        }

        $store->distance = 3218.69;   // metres — one mile

        foreach (['km', 'mi'] as $unit) {
            $this->useUnit($unit);
            $payload = (new \App\Http\Resources\Common\Store\StoreListResource($store))->toArray(request());

            // M4/M5 — both keys always, so a client picks by what /config told it.
            $this->assertSame(3.22, $payload['distance_km'], $unit);
            $this->assertSame(2.0, $payload['distance_mi'], $unit);
        }

        // Only the label follows the setting.
        $this->useUnit('mi');
        $payload = (new \App\Http\Resources\Common\Store\StoreListResource($store))->toArray(request());
        $this->assertSame('2 mi', $payload['distance_label']);
    }
}
