<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Services\System\DistanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Ride Fare Setup subtitle names a distance unit, and it used to name a hard-coded one.
 *
 * On a miles install it read "…to the per-kilometre rate." directly above a field labelled
 * "Fare (Per mi)" — the page contradicting itself in two adjacent lines.
 */
class RideFareUnitLabelTest extends TestCase
{
    use DatabaseTransactions;

    private const KEY = 'Set what a ride costs in this zone, from the base fare to the per-:unit rate.';

    private function withUnit(string $unit): string
    {
        DB::table('business_settings')->updateOrInsert(['key' => 'distance_unit'], ['value' => $unit]);
        Helpers::clearBusinessSettingsCache();
        app(DistanceService::class)->forgetUnit();

        return app(DistanceService::class)->unitLabel();
    }

    protected function tearDown(): void
    {
        Helpers::clearBusinessSettingsCache();
        app(DistanceService::class)->forgetUnit();

        parent::tearDown();
    }

    public function test_the_subtitle_follows_the_configured_distance_unit(): void
    {
        foreach (['km', 'mi'] as $unit) {
            $label = $this->withUnit($unit);

            $this->assertSame($unit, $label);

            $sentence = translate(self::KEY, ['unit' => $label]);

            $this->assertStringContainsString('per-'.$unit, $sentence, "the subtitle must say per-$unit");
            $this->assertStringNotContainsString(':unit', $sentence, 'the placeholder must be substituted');
        }
    }

    /** The unit must never be written into the sentence again. */
    public function test_the_view_does_not_hard_code_a_distance_unit(): void
    {
        $blade = file_get_contents(
            base_path('Modules/RideShare/Resources/views/admin/fare-management/trip/create.blade.php')
        );

        // The page-header description line only.
        preg_match('/page-header-desc.*/', $blade, $m);
        $line = $m[0] ?? '';

        $this->assertStringContainsString('$distanceUnitLabel', $line);

        foreach (['kilometre', 'kilometer', 'per-km', 'per-mi', 'mile'] as $literal) {
            $this->assertStringNotContainsString($literal, strtolower($line),
                "the subtitle must not hard-code \"$literal\"");
        }
    }

    /** The composer that supplies it must keep covering this view. */
    public function test_the_view_is_given_the_unit_label(): void
    {
        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertStringContainsString(
            "'ride-share::admin.fare-management.trip.create'",
            $provider,
            'the view composer must still share $distanceUnitLabel with this view',
        );
    }
}
