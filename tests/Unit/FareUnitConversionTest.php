<?php

namespace Tests\Unit;

use App\Services\System\BusinessSettingService;
use App\Services\System\DistanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Port doc §3.4 — a per-unit rate meets a measured distance in exactly one way, everywhere.
 *
 * The delivery engine had this from the start. Rental and ride-share multiplied their rate by raw
 * kilometres whatever `distance_unit` said, so with Mile selected a customer was quoted a
 * per-kilometre fare presented as per-mile (QA cases TC_09 and TC_10).
 *
 * These pin the rule at the two levels that can regress: the shared conversion itself, and the
 * seven call sites that must route through it.
 */
class FareUnitConversionTest extends TestCase
{
    use DatabaseTransactions;

    private function setUnit(string $unit): void
    {
        DB::table('business_settings')->updateOrInsert(['key' => 'distance_unit'], ['value' => $unit]);
        // The settings layer memoises in a PHP static that Cache::flush() does not touch, and a
        // test run is one process — so both memos have to be dropped or the unit never moves.
        \App\CentralLogics\Helpers::clearBusinessSettingsCache();
        BusinessSettingService::forgetCache();
        app(DistanceService::class)->forgetUnit();
    }

    protected function tearDown(): void
    {
        $this->setUnit('km');
        parent::tearDown();
    }

    public function test_kilometres_are_left_alone_when_the_unit_is_km(): void
    {
        $this->setUnit('km');

        $this->assertSame(10.887, app(DistanceService::class)->chargeable(10.887));
    }

    public function test_kilometres_convert_to_miles_at_money_precision(): void
    {
        $this->setUnit('mi');

        // Money precision, not display precision: rounding the operand at 2dp moves the fare.
        $this->assertSame(6.7649, app(DistanceService::class)->chargeable(10.887));
        $this->assertSame(1.5534, app(DistanceService::class)->chargeable(2.5));
    }

    /**
     * The guard that actually catches a regression: every place a per-unit rate multiplies a
     * distance must go through chargeable(). A future edit that writes `$rate * $distance`
     * directly fails here rather than silently mispricing.
     */
    public function test_every_fare_site_converts_before_multiplying(): void
    {
        $sites = [
            'Modules/RideShare/Traits/TripManagement/CommonTrait.php' => 4,
            'Modules/Rental/Traits/TripLogicTrait.php' => 1,
            'Modules/Rental/Services/Cart/RentalCartService.php' => 1,
            'Modules/Rental/Services/Trip/TripService.php' => 1,
        ];

        foreach ($sites as $path => $expected) {
            $source = file_get_contents(base_path($path));

            $this->assertSame(
                $expected,
                substr_count($source, 'chargeable('),
                "$path must convert the distance at all $expected fare sites (port doc §3.4)",
            );

            // The raw forms these sites used to carry.
            foreach ([
                'base_fare_per_km * $distance_in_km',
                'base_fare_per_km * $distance;',
                'distance_price * $distance',
            ] as $rawMultiply) {
                $this->assertStringNotContainsString(
                    $rawMultiply,
                    $source,
                    "$path multiplies a rate by an unconverted distance",
                );
            }
        }
    }
}
