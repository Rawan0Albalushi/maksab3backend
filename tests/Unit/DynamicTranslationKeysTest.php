<?php

namespace Tests\Unit;

use App\Services\System\DistanceService;
use App\Services\System\MeasurementUnitService;
use App\Services\Zone\SurgePriceService;
use Tests\TestCase;

/**
 * Units and weekdays must never be looked up with translate().
 *
 * Both are DATA, not copy. isPersistableTranslationKey() refuses to keep either in the language
 * files -- unit symbols by an explicit rule, weekday names because the date library localises
 * them already. translate() does not fail loudly on a key it cannot find: it humanises the key
 * and writes that back. So a lookup here has two costs, and both are silent. "km" renders as
 * "Km" and "Sunday" stays English in every locale; and the junk key reappears in the language
 * file on every render, which is what TranslationKeyHygieneTest then reports.
 *
 * This happened twice -- once when a language cleaner stripped the unit rows (2026-09-07), and
 * again when the units were excluded from translation by policy but the call sites stayed.
 * The guard is therefore on the OUTPUT, not on the language file: what matters is that the label
 * a screen renders is the symbol itself.
 */
class DynamicTranslationKeysTest extends TestCase
{
    /** SI and imperial symbols are lower case; the humanised fallback is always wrong for them. */
    public function test_unit_labels_are_the_symbol_itself_and_stay_lowercase(): void
    {
        $labels = [
            'distance' => app(DistanceService::class)->unitLabel(),
            'weight' => app(MeasurementUnitService::class)->weightUnitLabel(),
            'dimension' => app(MeasurementUnitService::class)->dimensionUnitLabel(),
        ];

        foreach ($labels as $kind => $label) {
            $this->assertNotSame('', $label, "the $kind unit label is empty");
            $this->assertSame(
                mb_strtolower($label),
                $label,
                "the $kind unit label '$label' is capitalised — it went through translate(), which "
                . 'ucfirst()s any key the language file does not hold',
            );
        }

        $this->assertContains($labels['distance'], ['km', 'mi']);
        $this->assertContains($labels['weight'], ['kg', 'lb']);
        $this->assertContains($labels['dimension'], ['cm', 'in']);
    }

    /**
     * The language file must not hold them either: present, they are junk rows an admin sees in
     * the Language table and a cleaner deletes again on its next run.
     */
    public function test_unit_and_weekday_keys_are_absent_from_every_language_file(): void
    {
        // Symbols and weekday names only. "Min" is NOT here: capitalised, it is an ordinary
        // label meaning minimum or minutes ("Min:", "ETA Range Gap (Min)") on a dozen screens,
        // and isPersistableTranslationKey() keeps it. What was junk was the lowercase duplicate
        // the additional-delivery-charge list used to compose, which now reads eta_minute_unit.
        $banned = ['km', 'mi', 'kg', 'lb', 'cm', 'in',
            'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        foreach (['en', 'ar', 'bn'] as $locale) {
            $path = base_path("resources/lang/$locale/messages.php");

            if (! file_exists($path)) {
                continue;
            }

            $messages = include $path;

            foreach ($banned as $key) {
                $this->assertArrayNotHasKey(
                    $key,
                    $messages,
                    "$locale/messages.php holds '$key'. isPersistableTranslationKey() refuses it, so "
                    . 'something is still calling translate() with it and writing it back.',
                );
            }
        }
    }

    /** Weekday names come from the date library, localised, not from the language file. */
    public function test_weekday_labels_are_localised_by_the_date_library(): void
    {
        $service = app(SurgePriceService::class);

        $this->app->setLocale('en');
        $this->assertSame('Sunday', $service->weekdayLabel('Sunday'));

        $this->app->setLocale('ar');
        $arabic = $service->weekdayLabel('Sunday');
        $this->app->setLocale('en');

        $this->assertNotSame('Sunday', $arabic, 'the Arabic weekday is still the English name');
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $arabic);
    }

    /**
     * The ETA units DO belong in the language file — "min"/"hr"/"days" are words, unlike the
     * symbols above. What they must never hold is translate()'s humanised fallback.
     */
    public function test_eta_unit_keys_hold_a_duration_and_not_the_humanised_key(): void
    {
        foreach (['en', 'ar', 'bn'] as $locale) {
            $path = base_path("resources/lang/$locale/messages.php");

            if (! file_exists($path)) {
                continue;
            }

            $messages = include $path;

            foreach (['eta_minute_unit', 'eta_hour_unit', 'eta_day_unit'] as $key) {
                $this->assertArrayHasKey($key, $messages, "$locale is missing $key");
                $this->assertDoesNotMatchRegularExpression(
                    '/^Eta [a-z]+ unit$/i',
                    (string) $messages[$key],
                    "$locale/$key holds translate()'s humanised key instead of a duration",
                );
            }
        }
    }
}
