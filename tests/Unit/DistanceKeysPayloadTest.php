<?php

namespace Tests\Unit;

use App\Services\System\BusinessSettingService;
use App\Services\System\DistanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * QA case TC_15 — every payload that carries a distance carries the SAME three keys.
 *
 * Store payloads measure in metres, trips in kilometres. Both must describe a distance the same
 * way, or a client holding a bare number cannot tell what unit it is in — which is what the
 * rental trip payload did before this.
 */
class DistanceKeysPayloadTest extends TestCase
{
    use DatabaseTransactions;

    private function setUnit(string $unit): void
    {
        DB::table('business_settings')->updateOrInsert(['key' => 'distance_unit'], ['value' => $unit]);
        \App\CentralLogics\Helpers::clearBusinessSettingsCache();
        BusinessSettingService::forgetCache();
        app(DistanceService::class)->forgetUnit();
    }

    protected function tearDown(): void
    {
        $this->setUnit('km');
        parent::tearDown();
    }

    public function test_metres_and_kilometres_produce_the_same_shape(): void
    {
        $this->setUnit('km');

        $fromMetres = app(DistanceService::class)->keysFromMetres(3191);
        $fromKm = app(DistanceService::class)->keysFromKilometres(3.191);

        $this->assertSame(array_keys($fromMetres), array_keys($fromKm));
        $this->assertSame($fromMetres, $fromKm, 'the same journey must describe itself identically');
    }

    public function test_both_units_are_always_emitted_whatever_the_setting_says(): void
    {
        // N9 — an older build reading distance_km keeps working after the operator picks miles.
        foreach (['km', 'mi'] as $unit) {
            $this->setUnit($unit);
            $keys = app(DistanceService::class)->keysFromKilometres(3.191);

            $this->assertSame(3.19, $keys['distance_km']);
            $this->assertSame(1.98, $keys['distance_mi']);
        }
    }

    public function test_only_the_label_follows_the_setting(): void
    {
        $this->setUnit('km');
        $this->assertSame('3.19 km', app(DistanceService::class)->keysFromKilometres(3.191)['distance_label']);

        $this->setUnit('mi');
        $this->assertSame('1.98 mi', app(DistanceService::class)->keysFromKilometres(3.191)['distance_label']);
    }

    /** TC_57 — an unselected column is null before the accessor runs, however well translated. */
    public function test_the_active_zone_list_selects_the_translated_display_name(): void
    {
        $source = file_get_contents(base_path('app/Services/Zone/ZoneService.php'));

        $this->assertStringContainsString(
            "->select('id', 'name', 'display_name', 'coordinates')",
            $source,
            'getActiveList() must select display_name or the customer payload carries null',
        );

        $resource = file_get_contents(base_path('app/Http/Resources/Common/Zone/ZoneResource.php'));
        $this->assertStringContainsString("'display_name' => \$this->resource->display_name", $resource);
    }
}
