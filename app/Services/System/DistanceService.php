<?php

namespace App\Services\System;

use App\Services\BaseService;

/**
 * The distance-unit kernel. One place decides what a distance means and how it is shown.
 *
 * Resolve it inline where you need it — `app(DistanceService::class)->format($km)` — per the
 * architecture's rule 4. It is registered as a singleton in AppServiceProvider so the unit memo
 * survives across resolutions within a request.
 *
 * ---------------------------------------------------------------------------------------
 * PHASE-0 DECISION D1 — measured values vs. setup values
 * ---------------------------------------------------------------------------------------
 * Documentation/delivery-zone-suite-port.md §3.2. Written here rather than agreed in chat,
 * because getting it wrong is the difference between a zone that quotes one price and
 * charges another.
 *
 * There are exactly two kinds of distance in this system, and they are stored differently:
 *
 *   MEASURED — `orders.distance`, trip distances, ST_Distance_Sphere output, the haversine in
 *              the Builder checkout. ALWAYS KILOMETRES, whatever the setting says. On a unit
 *              switch the stored number does not change; it is converted at the boundary, for
 *              display and for meeting a rate.
 *
 *   SETUP    — `per_km_shipping_charge`, `per_km_charge`, `starting_coverage_area`,
 *              `maximum_coverage_area`, weight and dimension bands. Stored EXACTLY AS TYPED.
 *              On a unit switch the stored number does not change either — its MEANING
 *              changes. A `400` band becomes 400 miles. Nothing converts it, on save or on
 *              read.
 *
 * A measured distance and a setup value therefore only ever meet AFTER the measured one has
 * been converted into the setup one's unit. That happens in exactly two places:
 *
 *   1. DeliveryChargeService::chargeableBase()   — before every rate multiplication
 *   2. DmVehicleService::coverageVehicle()       — before every coverage band comparison
 *
 * Nothing else compares or multiplies across the two kinds. This model needs no data
 * migration, no reverse conversion, and no per-column unit flag.
 *
 * REVERSE CONVERSION IS DELIBERATELY ABSENT (mistake M3). Under the setup-value model a
 * `display -> km` direction has no caller: an admin types a setup value and it is stored
 * verbatim. If you find yourself needing one, the model above has been broken somewhere —
 * find that instead of adding the method.
 *
 * PRECISION. chargeable() rounds at 4 dp because its result feeds a multiplication; rounding
 * the operand at display precision moves the fare. Display rounds at 2 dp. No call site
 * passes its own precision for money.
 */
class DistanceService extends BaseService
{
    public const KM = 'km';

    public const MI = 'mi';

    /** The factor, written once. A grep for these numbers must return only these two lines. */
    public const MI_PER_KM = 0.621371192;

    public const KM_PER_MI = 1.609344;

    public const PRECISION_DISPLAY = 2;

    public const PRECISION_MONEY = 4;

    /**
     * Static rather than an instance property so a fresh resolution cannot lose the memo —
     * belt and braces alongside the singleton binding.
     */
    private static ?string $unitMemo = null;

    /**
     * The configured unit.
     *
     * Read through BusinessSettingService (itself cached) plus a memo, because this is called
     * from inside per-row formatters — a store listing would otherwise issue one settings read
     * per card (mistake M8). Defaults to kilometres when the row is absent, so an install that
     * has not run the seeder still behaves.
     */
    public function unit(): string
    {
        if (self::$unitMemo !== null) {
            return self::$unitMemo;
        }

        $unit = (string) (app(BusinessSettingService::class)->value('distance_unit', false) ?? '');

        return self::$unitMemo = $unit === self::MI ? self::MI : self::KM;
    }

    /**
     * Cleared from Helpers::clearBusinessSettingsCache(), which BusinessSetting::saved()
     * already calls — so every write path is covered, and a save-then-render request cannot
     * read a stale unit.
     */
    public function forgetUnit(): void
    {
        self::$unitMemo = null;
    }

    /** Translated, so apps and blades carry no km/mi mapping of their own. */
    /**
     * The unit symbol, verbatim.
     *
     * Not routed through translate(): `km` and `mi` are international symbols, not prose, and the
     * translator ucfirst()s any key it has no entry for -- which rendered every distance as
     * "10 Km". SI symbols are lower case in every locale that uses them.
     */
    public function unitLabel(): string
    {
        return $this->unit();
    }

    /** Kilometres in, the configured unit out. */
    public function convert(mixed $kilometres, ?int $precision = null): float
    {
        $value = (float) $kilometres;
        $precision ??= self::PRECISION_DISPLAY;

        if ($this->unit() === self::MI) {
            $value *= self::MI_PER_KM;
        }

        return round($value, $precision);
    }

    /** "6.21 mi" — for humans. */
    public function format(mixed $kilometres, ?int $precision = null): string
    {
        return $this->convert($kilometres, $precision).' '.$this->unitLabel();
    }

    /**
     * The operand every rate multiplication and every coverage-band comparison uses.
     * Four decimal places: this number is multiplied, not displayed.
     */
    public function chargeable(mixed $kilometres): float
    {
        return $this->convert($kilometres, self::PRECISION_MONEY);
    }

    /**
     * Both units, always, whatever the setting says — a client picks by what /config told it
     * (§14.6, N9). That is precisely why this uses MI_PER_KM directly instead of calling
     * convert(): the `distance_mi` key must be miles even on a kilometres install, or a client
     * doing payload["distance_" . unit] gets the wrong number (M5).
     *
     * Key suffixes match the enum values exactly: distance_km / distance_mi.
     */
    public function keysFromMetres(mixed $metres): array
    {
        return $this->keysFromKilometres((float) $metres / 1000);
    }

    /**
     * The same three keys from a distance already held in KILOMETRES.
     *
     * Store payloads measure in metres (`ST_Distance_Sphere`); trips store kilometres. Both need
     * an identical key shape, so the shape is defined once here and `keysFromMetres()` converts
     * into it — otherwise two payloads describing the same journey drift apart in naming or
     * rounding.
     *
     * `distance_km` and `distance_mi` are ALWAYS both emitted, whatever `distance_unit` says, so
     * a client picks by what /config told it and an older build reading `distance_km` keeps
     * working (N9). Only `distance_label` follows the setting.
     */
    public function keysFromKilometres(mixed $kilometres): array
    {
        $kilometres = (float) $kilometres;

        return [
            'distance_km' => round($kilometres, self::PRECISION_DISPLAY),
            'distance_mi' => round($kilometres * self::MI_PER_KM, self::PRECISION_DISPLAY),
            'distance_label' => $this->format($kilometres),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | The switch guard — port doc §3.5, mistake M6
    |--------------------------------------------------------------------------
    |
    | Changing `distance_unit` reinterprets two independent sets of stored numbers at once:
    | per-unit RATES and coverage BANDS. Nothing is converted by default, so the switch is
    | silent and total — a 50 km delivery priced at 250 becomes 174.27 the moment it is saved.
    |
    | So the change is never a side effect of saving a settings page. It has its own endpoint,
    | its own confirmation quoting the operator's own numbers, and an optional conversion.
    */

    /** The columns holding a per-unit RATE. Setup values: stored as typed, meaning re-read. */
    private const RATE_COLUMNS = [
        ['table' => 'module_zone', 'column' => 'per_km_shipping_charge'],
        ['table' => 'stores', 'column' => 'per_km_shipping_charge'],
        // Not in the port doc's list because delivery rules did not exist when it was written.
        // Leaving it out would convert the pivot's rates and strand every rule's.
        ['table' => 'delivery_rules', 'column' => 'per_km_charge'],
    ];

    /** The columns holding a coverage BAND. */
    private const BAND_COLUMNS = [
        ['table' => 'd_m_vehicles', 'column' => 'starting_coverage_area'],
        ['table' => 'd_m_vehicles', 'column' => 'maximum_coverage_area'],
    ];







}
