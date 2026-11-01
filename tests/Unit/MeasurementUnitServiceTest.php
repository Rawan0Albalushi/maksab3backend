<?php

namespace Tests\Unit;

use App\CentralLogics\Helpers;
use App\Services\System\MeasurementUnitService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * S10c — the weight and dimension units, product decision 4.
 *
 * The same storage model distance uses: classes are SETUP values, stored exactly as typed, and a
 * switch changes their meaning rather than their value. The difference the dialog states out
 * loud is that **no fee moves** — nothing prices on weight or dimension yet.
 */
class MeasurementUnitServiceTest extends TestCase
{
    use DatabaseTransactions;

    private MeasurementUnitService $units;

    protected function setUp(): void
    {
        parent::setUp();

        $this->units = app(MeasurementUnitService::class);
        $this->useUnits('kg', 'in');
    }

    protected function tearDown(): void
    {
        $this->useUnits('kg', 'in');

        parent::tearDown();
    }

    private function useUnits(string $weight, string $dimension): void
    {
        \DB::table('business_settings')->updateOrInsert(['key' => 'weight_unit'], ['value' => $weight]);
        \DB::table('business_settings')->updateOrInsert(['key' => 'dimension_unit'], ['value' => $dimension]);
        Helpers::clearBusinessSettingsCache();
        $this->units->forgetUnits();
    }

    // ------------------------------------------------------------------ reading the setting

    public function test_the_seeded_units_are_the_ones_the_screens_already_used(): void
    {
        // The migration seeds kg and in on purpose: those are what S4a's screens have been
        // capturing, and seeding anything else would silently reinterpret every saved class.
        $this->assertSame('kg', $this->units->weightUnit());
        $this->assertSame('in', $this->units->dimensionUnit());
    }

    public function test_an_unrecognised_stored_value_falls_back_to_the_default(): void
    {
        $this->useUnits('stones', 'cubits');

        $this->assertSame('kg', $this->units->weightUnit());
        $this->assertSame('in', $this->units->dimensionUnit());
    }

    // ------------------------------------------------------------------ detecting a real change




    // ------------------------------------------------------------------ the storage model





    // ------------------------------------------------------------------ the preview



    /**
     * The label is the symbol itself, not a translate() lookup.
     *
     * isPersistableTranslationKey() refuses to keep unit symbols in the language files, and
     * translate() ucfirst()s a key it cannot find -- so asserting against translate('messages.kg')
     * asserted that the label was "Kg", which is what every weight on the panel rendered as.
     */
    public function test_the_label_follows_the_setting(): void
    {
        $this->assertSame('kg', $this->units->weightUnitLabel());

        $this->useUnits('lb', 'cm');

        $this->assertSame('lb', $this->units->weightUnitLabel());
        $this->assertSame('cm', $this->units->dimensionUnitLabel());
    }
}
