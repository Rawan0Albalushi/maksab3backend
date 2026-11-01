<?php

namespace App\Services\Order;

use App\Models\DeliveryRule;
use App\Services\BaseService;
use App\Services\DeliveryMan\DmVehicleService;
use App\Services\Parcel\ParcelCategoryService;
use App\Services\System\BusinessSettingService;
use App\Services\System\DistanceService;
use App\Services\Zone\DeliveryRuleDimensionChargeService;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\DeliveryRuleWeightChargeService;

/**
 * THE delivery-charge engine. Steps 1-6 of the canonical fee pipeline
 * (Documentation/delivery-zone-suite-port.md §12).
 *
 * Exactly one method in this codebase multiplies a rate by a distance, and it is
 * chargeableBase() below. Every caller — customer place_order, parcel, POS, the Builder
 * storefront checkout, order-edit — routes through quote(). Non-negotiable N1.
 *
 *   1. Resolve (zone, module) for the store            — caller supplies both
 *   2. Base charge
 *        a. store self-delivery   → the store's own rates
 *        b. active delivery rule  → DeliveryRuleService::baseCharge()   [S4 — not yet wired]
 *        c. parcel category       → ParcelCategory rates, business-setting fallback
 *        d. otherwise             → module_zone pivot pricing
 *   3. + vehicle extra charge     (DmVehicleService)
 *   4. + zone "increased delivery fee" percentage       [S4 — not yet wired]
 *   5. clamp to maximum
 *   6. + surge                    (resolved by the caller until S8 moves it in here)
 *   ── engine output; always >= the floor ──
 *
 * Steps 7-9 — free-delivery overrides, post-engine percentage discounts, and the saver
 * delivery type — are NOT here. They live in App\Traits\Order\DeliveryFeeTrait, which owns
 * the sequence so no caller inlines its own order (M11, M12).
 *
 * ---------------------------------------------------------------------------------------
 * PHASE-0 DECISION D2 — inbound `distance` is kilometres, always.
 * ---------------------------------------------------------------------------------------
 * `$context['distance']` is ALWAYS in kilometres, whatever `business_settings.distance_unit`
 * says. Clients post kilometres; ST_Distance_Sphere and haversine produce kilometres.
 * The setting never reinterprets this value — §3.6.
 *
 * The conversion to the unit a *rate* is quoted in happens in exactly one place,
 * chargeableBase(), via app(DistanceService::class)->chargeable(). A measured
 * distance and a setup value only ever meet after the measured one has been converted.
 * Rates (`per_km_shipping_charge`, `per_km_charge`) are SETUP values under D1: stored
 * exactly as typed, re-read in whatever unit the setting names, never converted.
 *
 * ---------------------------------------------------------------------------------------
 * COMPATIBILITY FLAGS — all retired as of 2026-09-03. `$context['compat']` is ignored.
 * ---------------------------------------------------------------------------------------
 * Before this class existed, four engines each hand-rolled the arithmetic above and they
 * did NOT agree. Consolidating them while silently adopting one engine's behaviour for all
 * four would have re-priced POS and parcel orders in a phase whose contract was "no
 * behaviour change" — M1 restated. So each divergence was named as a flag, every caller
 * passed the flags matching what it did before, and each was retired in a later phase that
 * measured the change first. Every branch now runs the same arithmetic.
 *
 *   apply_max_clamp        Parcel (E2) never clamped to a maximum at all. Could not retire in
 *                          S5 because parcel did not honour delivery rules yet; it does now
 *                          (S14), and §12 step 5 applies to every branch. Measured before
 *                          removal: 0 of 108 parcel-module quotes moved, because a parcel
 *                          (zone, module) carries no maximum to clamp against.
 *
 *   apply_rounding         Only POS (E3) rounded its output, to config('round_up_to_digit')
 *                          decimal places. Retired by owner decision 2026-09-03: that config
 *                          IS the platform's decimal setting (`digit_after_decimal_point`),
 *                          so display already honours it and the engine rounding it a second
 *                          time was duplication. `orders.delivery_charge` is decimal(24,2),
 *                          which rounds identically on write — verified against PHP's round()
 *                          on the halfway cases — so no STORED fee moves. Measured: 38 of 532
 *                          in-memory quotes differ, by at most half a cent.
 *
 * A `compat` key left in a caller's context array is now inert, not an error, so nothing
 * outside this repo breaks on the way through.
 *
 * ---------------------------------------------------------------------------------------
 * S19 INVARIANT — an unavailable (zone, module) must never reach quote().
 * ---------------------------------------------------------------------------------------
 * A module is available in a zone only when that PAIR carries both an active delivery rule and
 * an active ETA configuration (`Zone::completeBetween()`). Step 2d above will happily price a
 * pair that carries neither, off the `module_zone` pivot — which is exactly how an unpriced pair
 * used to get a fee anyway, and why the gate could not be put here.
 *
 * The fallback stays. It is what historical orders re-price through, and ripping it out would
 * turn a missing setup into an exception at checkout rather than a zone the customer is never
 * routed into. The gate belongs to the CALLERS: the zone resolver and the module list decide
 * what a customer can reach, and order placement refuses a pair they would not have offered.
 * If a new caller reaches quote() for an unavailable pair, the bug is in that caller.
 */
class DeliveryChargeService extends BaseService
{
    public const SOURCE_SELF_DELIVERY = 'self_delivery';

    public const SOURCE_DELIVERY_RULE = 'delivery_rule';

    public const SOURCE_MODULE_ZONE = 'module_zone';

    public const SOURCE_PARCEL_CATEGORY = 'parcel_category';

    public const SOURCE_NONE = 'none';

    /**
     * THE fee engine. One method, every caller (§0.3, N1).
     *
     * Runs §12's canonical order: resolve the rates for this (zone, module), take the base,
     * add the vehicle extra, clamp, then surge. Steps 7-9 — free-delivery overrides, post-engine
     * discounts and the saver type — deliberately live in DeliveryFeeTrait, which owns that
     * sequence so no caller inlines its own order (M11, M12).
     *
     * Returns `floor` alongside the charge: it is the minimum whichever branch of step 2 ran, and
     * every post-engine discount must respect it. Returning it is how M7 stays fixed.
     */
    public function quote(array $context): array
    {
        $orderType = $context['order_type'] ?? 'delivery';
        $distanceKm = max(0.0, (float) ($context['distance'] ?? 0));

        if ($orderType === 'take_away') {
            return $this->emptyQuote($distanceKm);
        }

        // A parcel is priced by the delivery rule like any other order now. Its CATEGORY no longer
        // prices it — the category carries one flat charge that is ADDED below (parcel brief §1,
        // model (a); owner decision 2026-09-03).
        $rates = $this->deliveryRates($context);

        // Step 3 — REMOVED. Port doc A8: there is no vehicle-category charge.
        //
        // The lookup survives because A10 keeps vehicle categories as a DISPATCH concept: an
        // express order still reaches only deliverymen holding the matched category, so the
        // order still records which one its distance falls into. What is gone is the money.
        $vehicle = $rates['source'] === self::SOURCE_SELF_DELIVERY
            ? ['vehicle_id' => null]
            : ['vehicle_id' => $this->coverageVehicleId($distanceKm)];

        // Step 2 — the base, floored at the minimum. This floor is what every post-engine
        // discount must respect; returning it is how M7 stays fixed.
        $flat = $rates['flat'] ?? null;
        $base = $this->chargeableBase($distanceKm, $rates['per_unit'], $rates['minimum'], $flat);

        // Step 2d — the ADDITIVE parcel tiers, before the clamp, per the parcel brief's arithmetic:
        //
        //     base + weight + dimension + category = delivery charge, then clamp, surge, …
        //
        // All three are live now. The rule resolved for step 2b is handed down rather than looked
        // up again: it carries the two switches that decide whether weight and dimension are
        // charged at all, and resolving it twice for one quote is a duplicate query (rule 11).
        $base += $orderType === 'parcel' ? $this->parcelTierCharges($context, $rates['rule'] ?? null) : 0.0;

        // Step 5 — clamp to the maximum, before the vehicle extra is added, matching the
        // order all four engines used.
        //
        // A flat base is never clamped: the amount IS the answer for this order, and such a rule
        // carries no maximum at all (the column is nulled for every method but distance). Running
        // the clamp on it would compare against a zero maximum and wipe the charge out.
        // Every branch clamps now — the parcel exemption retired with S14's parcel tier.
        if ($flat === null) {
            $base = $this->clampToMaximum($base, $rates['minimum'], $rates['maximum']);
        }

        // A charge settled upstream replaces the computed base.
        $preset = $context['preset_delivery_charge'] ?? null;
        $charge = $preset !== null ? (float) $preset : $base;

        // Step 6 — surge. Applied after the clamp, so it applies to what the customer would
        // otherwise have paid. Never on self-delivery stores: they set their own rates and
        // are not covered by zone surge (§9.2).
        $surgeAmount = 0.0;
        if ($rates['source'] !== self::SOURCE_SELF_DELIVERY) {
            $surgeAmount = $this->surgeAmount($charge, $context['surge'] ?? null);
        }

        $charge += $surgeAmount;
        $original = $base + $this->surgeAmount($base, $rates['source'] !== self::SOURCE_SELF_DELIVERY ? ($context['surge'] ?? null) : null);

        return [
            'delivery_charge' => $charge,
            'original_delivery_charge' => $original,
            'base_delivery_charge' => $base,
            'surge_amount' => $surgeAmount,
            // Always 0.00 since A8. The key stays so nothing that reads it has to be found and
            // changed, and so a client rendering a vehicle line simply renders nothing (N9).
            'vehicle_extra' => 0.0,
            // Still resolved — A10 routes express orders by category (dispatch, not pricing).
            'vehicle_id' => $vehicle['vehicle_id'],
            'floor' => $rates['minimum'],
            'distance_km' => $distanceKm,
            'pricing_source' => $rates['source'],
        ];
    }

    /**
     * Step 2 — distance x rate, floored at the minimum.
     *
     * Public because the parity suite exercises it directly against the legacy arithmetic.
     */
    public function chargeableBase(float $distanceKm, float $perUnit, float $minimum, ?float $flat = null): float
    {
        // A flat base is an absolute amount for this order — an area's charge, a ZIP's charge, a
        // fixed rule amount — so the distance never multiplies it. The floor still applies.
        if ($flat !== null) {
            return max($flat, $minimum);
        }

        // §3.2 — the ONE place a measured distance meets a setup value. The distance arrives in
        // kilometres always (D2); the rate is quoted in whatever unit `distance_unit` names and is
        // never converted. So the distance is converted into the rate's unit immediately before
        // they multiply, at money precision — rounding the operand at display precision would
        // move the fare.
        $chargeable = app(DistanceService::class)->chargeable($distanceKm);

        $raw = $chargeable * $perUnit;

        return max($raw, $minimum);
    }

    /**
     * Step 5 — cap the charge at the zone's maximum.
     *
     * Two rules, and the legacy variants each got one of them wrong:
     *
     *  1. **`maximum = 0` means NO CAP**, not "cap at nothing". The customer API and POS ran a
     *     guard that clamped whenever `maximum >= minimum`, so a pivot with `maximum = 0` and
     *     `minimum = 0` capped the charge at zero — free delivery by accident. Eight of the
     *     eighteen live pivots have exactly that shape.
     *  2. **The cap never takes the charge below the floor.** The Builder storefront's guard
     *     ignored the minimum entirely, so a zone configured `minimum 30, maximum 20` charged 20
     *     — under its own stated minimum, breaking §12's "engine output; always >= the floor".
     *
     * So `max_clamp_guard` is retired by fixing the calculation rather than by picking a side.
     * A maximum below a minimum is a misconfiguration either way (the delivery-rule FormRequest
     * refuses it with `gte`; the older pivot screens never did), and the floor is the only
     * defensible answer when the two contradict.
     *
     * Safe to decide rather than escalate: no live pivot has `0 < maximum < minimum`, and the
     * eight with `maximum = 0` all carry `per_km = 0`, so every (pivot x distance) combination in
     * the live data returns what it returned before — verified, 0 of 126 changed.
     */
    private function clampToMaximum(float $charge, float $minimum, float $maximum): float
    {
        if ($maximum <= 0) {
            return $charge;
        }

        return max($minimum, min($charge, $maximum));
    }

    /**
     * The lowest a delivery in this (zone, module) may be priced at — port doc §8.1.
     *
     * The ADDITIONAL CHARGE feature needs this: a `slightly_delay` reduction larger than the
     * floor prices a delayed delivery at nothing. Three places ask the question — the setup
     * screen validating what an admin typed (§8.1), POS deciding whether to offer the saver
     * options at all (§8.2), and placement clamping the reduction it actually applies (§8.4a) —
     * and they must all get the same answer, so it is answered once, here, beside the rest of
     * the fee resolution.
     *
     * The ACTIVE DELIVERY RULE wins where there is one. Its `minimum_delivery_charge` is what
     * step 2b already floors the fee at, so it is the figure the saver must not cut through.
     * Without a rule the pivot's own `minimum_delivery_charge` stands — the saver floor an admin
     * set on Module Setup — and where neither exists the answer is NULL, meaning "nothing to
     * check against yet": skip the test, do not reject (§8.1).
     *
     * Note this is the pivot's `minimum_delivery_charge`, not its `minimum_shipping_charge`.
     * They are different columns: the second is the fee floor step 2c uses, the first is the
     * saver floor, and only the second has a value on every row.
     */
    public function deliveryFloor(mixed $zoneId, mixed $moduleId, mixed $pivot = null): ?float
    {
        $rule = app(DeliveryRuleService::class)->activeRule($zoneId, $moduleId);

        if ($rule) {
            return (float) $rule->minimum_delivery_charge;
        }

        $pivotFloor = $pivot?->minimum_delivery_charge;

        return $pivotFloor === null ? null : (float) $pivotFloor;
    }

    private function deliveryRuleRates(array $context): ?array
    {
        $pivot = $context['module_zone_pivot'] ?? null;
        $zoneId = $context['zone_id'] ?? ($pivot->zone_id ?? null);
        $moduleId = $context['module_id'] ?? ($pivot->module_id ?? null);

        $rules = app(DeliveryRuleService::class);
        $rule = $rules->activeRule($zoneId, $moduleId);

        if (! $rule) {
            return null;
        }

        $minimum = (float) $rule->minimum_delivery_charge;

        if ($rule->pricing_method === DeliveryRule::METHOD_DISTANCE) {
            return [
                'per_unit' => (float) ($rule->per_km_charge ?? 0),
                'minimum' => $minimum,
                'maximum' => (float) ($rule->maximum_delivery_charge ?? 0),
                'flat' => null,
                'source' => self::SOURCE_DELIVERY_RULE,
                'rule' => $rule,
            ];
        }

        // SECURITY (§5.4). A customer can post any area_id. Left unchecked they post the id of a
        // cheaper area in another zone and are charged its rate. Callers refuse such a pick with a
        // 403; this is the second line — a pick that is not this zone's is treated as no pick at
        // all, so the worst case is the rule's own floor rather than someone else's cheaper rate.
        // Belt and braces, because the engine has five callers and one forgetting is plausible.
        $areaId = $context['area_id'] ?? null;
        $zipCodeId = $context['zip_code_id'] ?? null;

        if (($areaId || $zipCodeId) && ! $rules->coverageBelongsToZone($zoneId, $areaId, $zipCodeId)) {
            $areaId = null;
            $zipCodeId = null;
        }

        return [
            'per_unit' => 0.0,
            'minimum' => $minimum,
            // Nothing to clamp: the amount IS the answer, and the rule's own floor already
            // applied inside baseChargeForRule().
            'maximum' => 0.0,
            'flat' => $rules->baseChargeForRule(
                $rule,
                max(0.0, (float) ($context['distance'] ?? 0)),
                $areaId,
                $zipCodeId,
            ),
            'source' => self::SOURCE_DELIVERY_RULE,
            'rule' => $rule,
        ];
    }

    private function deliveryRates(array $context): array
    {
        $store = $context['store'] ?? null;

        // `sub_self_delivery`, always. The Builder storefront used to read `self_delivery_system`
        // instead, which ignores a subscription that switched self-delivery off — divergence #5,
        // fixed ahead of S5. Four live stores charged 75.00 in-app and 0.00 on the storefront.
        if ($store && (int) ($store->sub_self_delivery ?? 0) === 1) {
            return [
                'per_unit' => (float) ($store->per_km_shipping_charge ?? 0),
                'minimum' => (float) ($store->minimum_shipping_charge ?? 0),
                'maximum' => (float) ($store->maximum_shipping_charge ?? 0),
                'flat' => null,
                'source' => self::SOURCE_SELF_DELIVERY,
            ];
        }

        // Step 2b — an ACTIVE delivery rule for this (zone, module) outranks the pivot. The
        // pivot stays underneath as 2c, so a zone with no rule prices exactly as it did before.
        $ruleRates = $this->deliveryRuleRates($context);

        if ($ruleRates) {
            return $ruleRates;
        }

        $pivot = $context['module_zone_pivot'] ?? null;

        // An unpriced (zone, module) yields zero rates rather than no quote at all, because
        // the vehicle extra still applies to it — POS charged that extra even where the pivot
        // was missing, and dropping it here would silently under-charge those orders.
        // Callers that must refuse outright short-circuit before reaching quote().
        if (! $pivot) {
            return [
                'per_unit' => 0.0,
                'minimum' => 0.0,
                'maximum' => 0.0,
                'flat' => null,
                'source' => self::SOURCE_NONE,
            ];
        }

        // A 'fixed' pivot charges its flat amount whatever the distance, which the legacy
        // engines expressed by loading the same number into all three slots — the
        // multiplication then loses to the minimum and the clamp is a no-op.
        if (($pivot->delivery_charge_type ?? 'fixed') !== 'distance') {
            $fixed = (float) ($pivot->fixed_shipping_charge ?? 0);

            return [
                'per_unit' => $fixed,
                'minimum' => $fixed,
                'maximum' => $fixed,
                'flat' => null,
            'source' => self::SOURCE_MODULE_ZONE,
            ];
        }

        return [
            'per_unit' => (float) ($pivot->per_km_shipping_charge ?? 0),
            'minimum' => (float) ($pivot->minimum_shipping_charge ?? 0),
            'maximum' => (float) ($pivot->maximum_shipping_charge ?? 0),
            'flat' => null,
            'source' => self::SOURCE_MODULE_ZONE,
        ];
    }

    /**
     * Step 2c. Parcel prices from its category, falling back to the platform-wide setting.
     *
     * Parcel now honours delivery rules (settled at the S0 gate, resolving §16.4.1's open
     * question), so from S5 an active rule for the (zone, parcel-module) wins here and this
     * category pricing becomes the fallback below it — the same relationship the module_zone
     * pivot has. Until then it is the only parcel pricing there is.
     */
    /**
     * What the chosen parcel category ADDS — one flat amount, never a rate.
     *
     * This replaces the whole of the old `parcelRates()`, which let a category price the parcel
     * outright from its own per-km and minimum and never consulted the delivery rule. Under the
     * additive model the rule prices the parcel and this stacks on top, exactly as a weight band
     * or a dimension class does.
     *
     * The two deprecated columns and the two global `parcel_*_shipping_charge` settings are no
     * longer read by anything.
     */
    private function parcelCategoryCharge(array $context): float
    {
        $categoryId = $context['parcel_category_id'] ?? null;

        if (! $categoryId) {
            return 0.0;
        }

        return (float) (app(ParcelCategoryService::class)->find($categoryId)?->charge ?? 0);
    }

    /**
     * Step 2d in full — what the category, the weight band and the size class ADD.
     *
     * The three stack; none of them replaces the base. A parcel with no selections adds nothing
     * and is priced by the rule alone, which is why every branch below returns 0.0 rather than
     * refusing.
     *
     * THE TWO SWITCHES ARE HONOURED HERE, NOT AT THE CALLER. `weight_charge_status` and
     * `dimension_charge_status` are properties of the RULE — the wizard's two parcel steps — and
     * a charge row survives its switch being turned off (DeliveryRuleWeightChargeService clears
     * rows on save, but a rule deactivated another way keeps them). So a posted `weight_id` must
     * cost nothing while the switch is off, or a client could charge a customer for a tier the
     * admin had disabled just by keeping an id it fetched earlier.
     *
     * The same pair gates `GET parcel-weight` / `GET parcel-dimension`, so what a customer is
     * offered and what they can be charged for cannot disagree.
     *
     * There is no rule at all on a pivot-priced (zone, module). The category still applies —
     * it is the category's own charge, not the rule's — but weight and dimension cannot, because
     * the amounts live on a rule that does not exist.
     */
    private function parcelTierCharges(array $context, ?DeliveryRule $rule): float
    {
        $total = $this->parcelCategoryCharge($context);

        if (! $rule) {
            return $total;
        }

        if ($rule->weight_charge_status) {
            $total += app(DeliveryRuleWeightChargeService::class)
                ->chargeForWeight($rule->id, $context['weight_id'] ?? null);
        }

        if ($rule->dimension_charge_status) {
            $total += app(DeliveryRuleDimensionChargeService::class)
                ->chargeForDimension($rule->id, $context['dimension_id'] ?? null);
        }

        return $total;
    }

    /**
     * Which coverage band this distance falls into — for DISPATCH, not for pricing (A8, A10).
     *
     * The distance conversion lands here too: a coverage band is a setup value and stays as
     * typed, so the measured distance is what moves to meet it (§3.4).
     */
    private function coverageVehicleId(float $distanceKm): mixed
    {
        return app(DmVehicleService::class)->coverageVehicle($distanceKm)?->id;
    }

    /**
     * Step 6. Zero on a zero charge — nothing is surged onto a free delivery.
     */
    public function surgeAmount(float $charge, ?array $surge): float
    {
        $price = (float) ($surge['price'] ?? 0);

        if ($price <= 0 || $charge <= 0) {
            return 0.0;
        }

        return ($surge['price_type'] ?? 'amount') === 'percent'
            ? ($charge * $price) / 100
            : $price;
    }

    private function emptyQuote(float $distanceKm): array
    {
        return [
            'delivery_charge' => 0.0,
            'original_delivery_charge' => 0.0,
            'base_delivery_charge' => 0.0,
            'surge_amount' => 0.0,
            'vehicle_extra' => 0.0,
            'vehicle_id' => null,
            'floor' => 0.0,
            'distance_km' => $distanceKm,
            'pricing_source' => self::SOURCE_NONE,
        ];
    }
}
