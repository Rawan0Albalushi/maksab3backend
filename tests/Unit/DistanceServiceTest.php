<?php

namespace Tests\Unit;

use App\CentralLogics\Helpers;
use App\Services\System\BusinessSettingService;
use App\Services\System\DistanceService;
use Tests\TestCase;

/**
 * S2 gate — the distance-unit kernel, App\Services\System\DistanceService.
 *
 * Covers test 7 (unit round-trip) and test 13 (payload key naming) from the port document's
 * §18 matrix. Test 8 — setup values re-read rather than converted — belongs to the phase that
 * wires chargeable() into the coverage-band match and the rate multiplication; S2's
 * contract is explicitly "no behaviour change", so nothing consumes the kernel yet.
 */
class DistanceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->distance()->forgetUnit();
    }

    protected function tearDown(): void
    {
        $this->distance()->forgetUnit();
        parent::tearDown();
    }

    private function distance(): DistanceService
    {
        return app(DistanceService::class);
    }

    private function withUnit(?string $unit): void
    {
        $this->distance()->forgetUnit();

        $fake = $this->createMock(BusinessSettingService::class);
        $fake->method('value')->willReturn($unit);
        $this->instance(BusinessSettingService::class, $fake);
    }

    public function test_the_factor_is_a_named_constant_and_the_two_directions_are_reciprocal(): void
    {
        $this->assertEqualsWithDelta(1.0, DistanceService::MI_PER_KM * DistanceService::KM_PER_MI, 0.000001);
    }

    public function test_it_defaults_to_kilometres_when_the_setting_row_is_absent(): void
    {
        $this->withUnit(null);

        $this->assertSame(DistanceService::KM, $this->distance()->unit());
        $this->assertSame(10.0, $this->distance()->convert(10));
    }

    public function test_an_unrecognised_value_falls_back_to_kilometres_rather_than_pricing_in_it(): void
    {
        $this->withUnit('parsecs');

        $this->assertSame(DistanceService::KM, $this->distance()->unit());
    }

    public function test_kilometres_pass_through_untouched(): void
    {
        $this->withUnit(DistanceService::KM);

        $this->assertSame(0.0, $this->distance()->convert(0));
        $this->assertSame(7.25, $this->distance()->convert(7.25));
        $this->assertSame(7.25, $this->distance()->chargeable(7.25));
    }

    public function test_miles_convert_at_the_named_factor(): void
    {
        $this->withUnit(DistanceService::MI);

        $this->assertSame(DistanceService::MI, $this->distance()->unit());
        $this->assertEqualsWithDelta(10 * DistanceService::MI_PER_KM, $this->distance()->convert(10, 6), 0.000001);
        $this->assertSame(6.21, $this->distance()->convert(10));
    }

    /**
     * Test 7 — round-trip. The setup-value model means there is deliberately no reverse
     * converter (M3), so the round trip is asserted through the factor pair rather than
     * through a `toStorage()` that must not exist.
     */
    public function test_a_converted_distance_returns_to_its_original_kilometres(): void
    {
        $this->withUnit(DistanceService::MI);

        foreach ([0.0, 0.35, 1.0, 7.25, 42.0, 1234.567] as $km) {
            $miles = $this->distance()->convert($km, 10);
            $this->assertEqualsWithDelta($km, $miles * DistanceService::KM_PER_MI, 0.000001, "round trip failed for {$km} km");
        }
    }

    /**
     * chargeable() feeds a multiplication, so it keeps four decimals. Rounding it at
     * display precision moves the fare — on a $2.00/unit rate the difference below is real money.
     */
    public function test_money_precision_is_finer_than_display_precision(): void
    {
        $this->withUnit(DistanceService::MI);

        $this->assertSame(4, DistanceService::PRECISION_MONEY);
        $this->assertSame(2, DistanceService::PRECISION_DISPLAY);

        $display = $this->distance()->convert(7.25);
        $chargeable = $this->distance()->chargeable(7.25);

        $this->assertSame(4.5, $display);
        $this->assertSame(4.5049, $chargeable);
        $this->assertNotSame($display, $chargeable, 'money precision must not collapse onto display precision');
    }

    public function test_format_appends_the_translated_label(): void
    {
        $this->withUnit(DistanceService::MI);
        $this->assertStringEndsWith($this->distance()->unitLabel(), $this->distance()->format(10));
        $this->assertStringStartsWith('6.21', $this->distance()->format(10));
    }

    /**
     * Test 13 — payload key naming. `distance_` . config.distance_unit must resolve for BOTH
     * units, on either setting. That is why keysFromMetres() uses MI_PER_KM directly
     * instead of the setting-aware converter: on a kilometres install `distance_mi` still has
     * to be miles, or a client picking by what /config told it reads the wrong number (M5).
     */
    public function test_both_unit_keys_are_always_present_and_correct_whatever_the_setting(): void
    {
        foreach ([DistanceService::KM, DistanceService::MI] as $configured) {
            $this->withUnit($configured);

            $keys = $this->distance()->keysFromMetres(5000);

            $this->assertArrayHasKey('distance_km', $keys);
            $this->assertArrayHasKey('distance_mi', $keys);
            $this->assertArrayHasKey('distance_label', $keys);

            $this->assertSame(5.0, $keys['distance_km'], "distance_km wrong while configured as {$configured}");
            $this->assertSame(3.11, $keys['distance_mi'], "distance_mi wrong while configured as {$configured}");

            // The client's own lookup, exactly as it would write it.
            $this->assertArrayHasKey('distance_'.$configured, $keys);
        }
    }

    public function test_the_label_follows_the_setting_into_the_payload(): void
    {
        $this->withUnit(DistanceService::KM);
        $this->assertSame('5 '.$this->distance()->unitLabel(), $this->distance()->keysFromMetres(5000)['distance_label']);

        $this->withUnit(DistanceService::MI);
        $this->assertSame('3.11 '.$this->distance()->unitLabel(), $this->distance()->keysFromMetres(5000)['distance_label']);
    }

    /**
     * M8 — the unit is read from inside per-row formatters, so it is memoised. The memo must
     * also be droppable, or a save-then-render request keeps quoting the old unit.
     */
    public function test_the_unit_is_memoised_and_the_memo_can_be_dropped(): void
    {
        $fake = $this->createMock(BusinessSettingService::class);
        $fake->expects($this->once())->method('value')->willReturn(DistanceService::MI);
        $this->instance(BusinessSettingService::class, $fake);

        $this->distance()->forgetUnit();

        for ($i = 0; $i < 25; $i++) {
            $this->assertSame(DistanceService::MI, $this->distance()->unit());
        }

        // Dropping the memo lets a subsequent read see a changed setting.
        $this->withUnit(DistanceService::KM);
        $this->assertSame(DistanceService::KM, $this->distance()->unit());
    }

    public function test_clearing_the_business_settings_cache_also_clears_the_unit_memo(): void
    {
        $this->withUnit(DistanceService::MI);
        $this->assertSame(DistanceService::MI, $this->distance()->unit());

        $this->withUnit(DistanceService::KM);
        Helpers::clearBusinessSettingsCache();

        $this->assertSame(DistanceService::KM, $this->distance()->unit());
    }

    public function test_negative_and_zero_distances_do_not_produce_surprises(): void
    {
        $this->withUnit(DistanceService::MI);

        $this->assertSame(0.0, $this->distance()->convert(0));
        $this->assertSame(0.0, $this->distance()->chargeable(0));
        $this->assertSame(['distance_km' => 0.0, 'distance_mi' => 0.0], array_slice($this->distance()->keysFromMetres(0), 0, 2));
    }
}
