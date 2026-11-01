<?php

namespace Tests\Unit;

use App\Models\DMVehicle;
use App\Models\Store;
use App\Services\DeliveryMan\DmVehicleService;
use App\Services\Order\DeliveryChargeService;
use Tests\TestCase;

/**
 * S1 gate — engine parity.
 *
 * The four in-scope fee engines were consolidated onto DeliveryChargeService. S1's contract
 * is NO BEHAVIOUR CHANGE, so parity here means "the engine reproduces each legacy call site
 * exactly", not yet "all four agree with each other" — they demonstrably did not agree, and
 * the disagreements are carried as named compat flags until the phase that retires each one.
 *
 * legacyCustomerApi/legacyPos/legacyBuilder below are the arithmetic exactly as it stood
 * before consolidation, transcribed from:
 *   E1  PlaceNewOrderTrait::getDeliveryCharge()      (customer API + parcel)
 *   E3  PlaceNewOrderTrait::calculatePosDeliveryFee()
 *   E5  CheckoutProvider::…boundedDistanceFee()
 *
 * As each compat flag retired, the corresponding legacy* method here was deleted with it and its
 * assertions became the cross-engine parity the port document asks for (test 1). Both flags are
 * now retired — `apply_max_clamp` in S14, `apply_rounding` on 2026-09-03 — so `legacyPos` is gone
 * and POS is compared against the customer API rather than against its own old arithmetic.
 */
class DeliveryChargeEngineParityTest extends TestCase
{
    /**
     * ZERO since port doc A8 — the vehicle-category charge is gone from the fee pipeline.
     *
     * The constant survives rather than being deleted from eight assertions, so each still reads
     * as "the legacy base plus the vehicle extra" and the one thing that changed is visible in
     * one place. The MATCHED VEHICLE is still asserted separately: A10 keeps categories as the
     * routing key for express orders, so `vehicle_id` must still come back.
     */
    private const VEHICLE_EXTRA = 0.0;

    private const VEHICLE_ID = 42;

    /** Distances in kilometres, always — decision D2. */
    private const DISTANCES = [0.0, 0.4, 1.0, 3.75, 12.0, 60.0];

    /** [per_unit, minimum, maximum] — deliberately includes minimum > maximum. */
    private const RATE_SETS = [
        [2.0, 10.0, 100.0],
        [0.0, 0.0, 0.0],
        [5.0, 30.0, 20.0],
        [1.25, 0.0, 15.0],
        [3.0, 5.0, 0.0],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $vehicle = new DMVehicle;
        $vehicle->forceFill(['id' => self::VEHICLE_ID, 'extra_charges' => self::VEHICLE_EXTRA]);

        $fake = $this->createMock(DmVehicleService::class);
        $fake->method('coverageVehicle')->willReturn($vehicle);

        $this->instance(DmVehicleService::class, $fake);
    }

    public function test_customer_api_distance_pricing_matches_the_legacy_engine(): void
    {
        foreach (self::DISTANCES as $distance) {
            foreach (self::RATE_SETS as [$perKm, $min, $max]) {
                $pivot = $this->pivot('distance', $perKm, $min, $max);

                $quote = $this->engine()->quote([
                    'order_type' => 'delivery',
                    'distance' => $distance,
                    'store' => $this->store([]),
                    'module_zone_pivot' => $pivot,
                ]);

                $this->assertEqualsWithDelta(
                    $this->legacyCustomerApi($distance, $perKm, $min, $max),
                    $quote['delivery_charge'],
                    0.0001,
                    "customer API diverged at distance {$distance}, rate {$perKm}, min {$min}, max {$max}",
                );
            }
        }
    }

    public function test_customer_api_fixed_pricing_matches_the_legacy_engine(): void
    {
        foreach (self::DISTANCES as $distance) {
            foreach ([0.0, 12.0, 45.5] as $fixed) {
                $quote = $this->engine()->quote([
                    'order_type' => 'delivery',
                    'distance' => $distance,
                    'store' => $this->store([]),
                    'module_zone_pivot' => $this->pivot('fixed', 0, 0, 0, $fixed),
                ]);

                // A fixed pivot charged its flat amount plus the vehicle extra, whatever the
                // distance. That is what the three-slot loading in deliveryRates() preserves.
                $this->assertEqualsWithDelta(
                    $fixed + self::VEHICLE_EXTRA,
                    $quote['delivery_charge'],
                    0.0001,
                    "fixed pricing diverged at distance {$distance}, fixed {$fixed}",
                );
            }
        }
    }

    public function test_self_delivery_store_carries_no_vehicle_extra_and_no_surge(): void
    {
        $store = $this->store([
            'self_delivery_system' => 1,
            'per_km_shipping_charge' => 4.0,
            'minimum_shipping_charge' => 10.0,
            'maximum_shipping_charge' => 100.0,
        ]);

        $quote = $this->engine()->quote([
            'order_type' => 'delivery',
            'distance' => 5.0,
            'store' => $store,
            'module_zone_pivot' => $this->pivot('distance', 99.0, 99.0, 999.0),
            'surge' => ['price' => 50.0, 'price_type' => 'percent'],
        ]);

        $this->assertSame(20.0, $quote['delivery_charge'], 'the store\'s own rate must win');
        $this->assertSame(0.0, $quote['vehicle_extra']);
        $this->assertNull($quote['vehicle_id']);
        $this->assertSame(0.0, $quote['surge_amount'], 'zone surge does not reach a self-delivery store');
    }

    /**
     * This is now CROSS-ENGINE PARITY rather than a legacy comparison, which is what this file
     * said would happen: "when a compat flag retires, the corresponding legacy* method is deleted
     * with it and the surviving assertions become the cross-engine parity the port document asks
     * for". `apply_rounding` was the last flag, retired 2026-09-03, so `legacyPos` — which was
     * `legacyCustomerApi` wrapped in a round() — is gone and POS must quote what the customer API
     * quotes, to the cent and beyond it.
     */
    public function test_pos_now_prices_on_the_customer_apis_terms(): void
    {
        foreach (self::DISTANCES as $distance) {
            foreach (self::RATE_SETS as [$perKm, $min, $max]) {
                $context = [
                    'order_type' => 'delivery',
                    'distance' => $distance,
                    'store' => $this->store([]),
                    'module_zone_pivot' => $this->pivot('distance', $perKm, $min, $max),
                    'surge' => null,
                ];

                // POS passes no flag now, so it enters the engine on exactly the customer API's
                // terms — and must land on the pre-consolidation customer-API arithmetic, which
                // never rounded. Asserting the two engine calls against each other would be a
                // tautology; the reference is what makes this mean something.
                $this->assertEqualsWithDelta(
                    $this->legacyCustomerApi($distance, $perKm, $min, $max),
                    $this->engine()->quote($context)['original_delivery_charge'],
                    0.0001,
                    "POS diverged at distance {$distance}, rate {$perKm}, min {$min}, max {$max}",
                );
            }
        }
    }

    /** A POS quote must carry full precision now — no rounding to the decimal setting. */
    public function test_pos_no_longer_rounds_to_the_decimal_setting(): void
    {
        // 4.125 km at 5.00/km is 20.625 — the old POS rounded that to 20.63.
        $quote = $this->engine()->quote([
            'order_type' => 'delivery',
            'distance' => 4.125,
            'store' => $this->store([]),
            'module_zone_pivot' => $this->pivot('distance', 5.0, 0.0, 1000.0),
            'surge' => null,
        ]);

        $this->assertEqualsWithDelta(20.625, $quote['delivery_charge'], 0.0000001);
        $this->assertNotEquals(round(20.625, (int) config('round_up_to_digit')), $quote['delivery_charge']);
    }

    /** A stale `compat` key from a caller outside this repo must be inert, not fatal. */
    public function test_a_leftover_compat_key_is_ignored_rather_than_honoured(): void
    {
        $context = [
            'order_type' => 'delivery',
            'distance' => 4.125,
            'store' => $this->store([]),
            'module_zone_pivot' => $this->pivot('distance', 5.0, 0.0, 1000.0),
            'surge' => null,
        ];

        $this->assertSame(
            $this->engine()->quote($context)['delivery_charge'],
            $this->engine()->quote($context + ['compat' => ['apply_rounding' => true]])['delivery_charge'],
        );
    }

    public function test_pos_charges_nothing_in_an_unpriced_zone(): void
    {
        $quote = $this->engine()->quote([
            'order_type' => 'delivery',
            'distance' => 5.0,
            'store' => $this->store([]),
            'module_zone_pivot' => null,
        ]);

        $this->assertEqualsWithDelta(self::VEHICLE_EXTRA, $quote['original_delivery_charge'], 0.0001);
    }

    public function test_builder_matches_the_legacy_engine_under_its_own_clamp_guard(): void
    {
        foreach (self::DISTANCES as $distance) {
            foreach (self::RATE_SETS as [$perKm, $min, $max]) {
                $quote = $this->engine()->quote([
                    'order_type' => 'delivery',
                    'distance' => $distance,
                    'store' => $this->store([]),
                    'module_zone_pivot' => $this->pivot('distance', $perKm, $min, $max),
                ]);

                $this->assertEqualsWithDelta(
                    $this->legacyBuilder($distance, $perKm, $min, $max) + self::VEHICLE_EXTRA,
                    $quote['delivery_charge'],
                    0.0001,
                    "Builder diverged at distance {$distance}, rate {$perKm}, min {$min}, max {$max}",
                );
            }
        }
    }

    /**
     * The disagreement the consolidation had to preserve rather than paper over: a zone whose
     * minimum sits above its maximum is priced differently by the two guards. If this test
     * ever starts failing because both sides return the same number, a compat flag was retired
     * without its migration — check S5 before "fixing" the assertion.
     */
    public function test_the_two_clamp_readings_now_agree_because_the_flag_is_gone(): void
    {
        $context = [
            'order_type' => 'delivery',
            'store' => $this->store([]),
            'distance' => 10.0,
            'module_zone_pivot' => $this->pivot('distance', 2.0, 10.0, 5.0),
        ];

        $customerApi = $this->engine()->quote($context);
        $builder = $this->engine()->quote($context);

        // Was the sharpest divergence in the estate: 10 km x 2.00 = 20.00, minimum 10, maximum 5.
        // The customer API declined to clamp (its guard wanted maximum >= minimum) and charged
        // 20.00; Builder clamped to 5.00 — under the zone's own stated minimum. Same zone, same
        // cart, a 15.00 difference, and both answers wrong.
        //
        // S5 retired `max_clamp_guard` by fixing the calculation: the cap applies, but never
        // below the floor. Both callers now get 10.00.
        $this->assertEqualsWithDelta(10.0 + self::VEHICLE_EXTRA, $customerApi['delivery_charge'], 0.0001);
        $this->assertEqualsWithDelta($customerApi['delivery_charge'], $builder['delivery_charge'], 0.0001);
    }

    /**
     * Divergence #5, fixed ahead of S5.
     *
     * A subscription-model store whose subscription switched self-delivery OFF still carries
     * `self_delivery_system = 1` on the row. The customer API reads `sub_self_delivery`, which
     * defers to the subscription, and prices from the zone. The Builder storefront used to read
     * the raw column, conclude the store delivered for itself, and then find its self-delivery
     * rates were all zero — so it charged nothing. Four live stores were shipping free.
     *
     * Both paths must now reach the same number.
     */
    public function test_a_subscription_store_that_lost_self_delivery_is_priced_from_the_zone(): void
    {
        $store = $this->store([
            'store_business_model' => 'subscription',
            'self_delivery_system' => 1,
            'per_km_shipping_charge' => 0.0,
            'minimum_shipping_charge' => 0.0,
            'maximum_shipping_charge' => 0.0,
        ]);
        $store->setRelation('store_sub', (new \App\Models\StoreSubscription)->forceFill(['self_delivery' => 0]));

        $this->assertSame(0, (int) $store->sub_self_delivery, 'the subscription must win over the column');

        $context = [
            'order_type' => 'delivery',
            'distance' => 5.0,
            'store' => $store,
            'module_zone_pivot' => $this->pivot('distance', 3.0, 10.0, 500.0),
        ];

        $api = $this->engine()->quote($context);
        // S5 retired the guard flag: the storefront's reading is now the only one, so both
        // call shapes are the same quote. Kept as a regression pin on that decision.
        $storefront = $this->engine()->quote($context);

        $this->assertSame(DeliveryChargeService::SOURCE_MODULE_ZONE, $api['pricing_source']);
        $this->assertSame(DeliveryChargeService::SOURCE_MODULE_ZONE, $storefront['pricing_source']);
        $this->assertEqualsWithDelta($api['delivery_charge'], $storefront['delivery_charge'], 0.0001);
        $this->assertEqualsWithDelta(15.0 + self::VEHICLE_EXTRA, $api['delivery_charge'], 0.0001);
    }

    public function test_take_away_is_never_charged(): void
    {
        $quote = $this->engine()->quote([
            'order_type' => 'take_away',
            'distance' => 25.0,
            'store' => $this->store([]),
            'module_zone_pivot' => $this->pivot('distance', 10.0, 50.0, 500.0),
        ]);

        $this->assertSame(0.0, $quote['delivery_charge']);
        $this->assertSame(0.0, $quote['original_delivery_charge']);
        $this->assertNull($quote['vehicle_id']);
    }

    public function test_a_preset_charge_replaces_the_base(): void
    {
        $quote = $this->engine()->quote([
            'order_type' => 'delivery',
            'distance' => 5.0,
            'store' => $this->store([]),
            'module_zone_pivot' => $this->pivot('distance', 2.0, 10.0, 100.0),
            'preset_delivery_charge' => 3.0,
        ]);

        $this->assertEqualsWithDelta(3.0 + self::VEHICLE_EXTRA, $quote['delivery_charge'], 0.0001);
        // The notional original is computed from the rates regardless of the preset.
        $this->assertEqualsWithDelta(10.0 + self::VEHICLE_EXTRA, $quote['original_delivery_charge'], 0.0001);
    }

    public function test_surge_is_applied_after_the_clamp_and_never_onto_a_zero_charge(): void
    {
        $engine = $this->engine();

        // 5 km x 2.00 = 10.00, clamped to the 8.00 maximum, +10% = 8.80. There is no vehicle
        // extra between the clamp and the surge any more (A8) — it used to make this 17.05.
        $surged = $engine->quote([
            'order_type' => 'delivery',
            'distance' => 5.0,
            'store' => $this->store([]),
            'module_zone_pivot' => $this->pivot('distance', 2.0, 0.0, 8.0),
            'surge' => ['price' => 10.0, 'price_type' => 'percent'],
        ]);

        $this->assertEqualsWithDelta(8.8, $surged['delivery_charge'], 0.0001);
        $this->assertEqualsWithDelta(0.8, $surged['surge_amount'], 0.0001);

        $this->assertSame(0.0, $engine->surgeAmount(0.0, ['price' => 25.0, 'price_type' => 'percent']));
    }

    public function test_the_vehicle_category_is_still_matched_but_never_charged(): void
    {
        // A8 removes the money, A10 keeps the category: an express order still has to reach a
        // deliveryman holding the matched one, so the quote must still say which it is.
        $quote = $this->engine()->quote([
            'order_type' => 'delivery',
            'distance' => 5.0,
            'store' => $this->store([]),
            'module_zone_pivot' => $this->pivot('distance', 2.0, 10.0, 100.0),
        ]);

        $this->assertSame(self::VEHICLE_ID, $quote['vehicle_id'], 'dispatch still needs the category');
        $this->assertSame(0.0, $quote['vehicle_extra'], 'and it must cost nothing');
        $this->assertEqualsWithDelta(10.0, $quote['delivery_charge'], 0.0001);
    }

    public function test_a_self_delivery_store_matches_no_vehicle_at_all(): void
    {
        $quote = $this->engine()->quote([
            'order_type' => 'delivery',
            'distance' => 5.0,
            'store' => $this->store(['self_delivery_system' => 1, 'per_km_shipping_charge' => 2.0]),
            'module_zone_pivot' => $this->pivot('distance', 9.0, 10.0, 100.0),
        ]);

        $this->assertNull($quote['vehicle_id'], 'a store delivering for itself needs no platform category');
    }

    /* ───────────────────────── the legacy arithmetic, verbatim ───────────────────────── */

    private function legacyCustomerApi(float $distance, float $perKm, float $min, float $max): float
    {
        $charge = ($distance * $perKm) > $min ? $distance * $perKm : $min;
        $charge = $this->clampAsOfS5($charge, $min, $max);

        return $charge + self::VEHICLE_EXTRA;
    }

    private function legacyBuilder(float $distance, float $perKm, float $min, float $max): float
    {
        $raw = $distance * $perKm;

        return $this->clampAsOfS5(max($raw, $min), $min, $max);
    }

    /**
     * The clamp as S5 settled it, and the ONE place these references deliberately depart from
     * the legacy arithmetic.
     *
     * The three legacy engines disagreed at two shapes and each was wrong at one of them:
     * `maximum = 0` capped the charge at zero for the customer API and POS, and `maximum < minimum`
     * charged under the stated floor for Builder. Neither is defensible, so S5 fixed the
     * calculation instead of picking a side — `0` means no cap, and the cap never crosses the
     * floor.
     *
     * This changes NO live fee: no pivot has `0 < maximum < minimum`, and the eight with
     * `maximum = 0` all carry `per_km = 0`. The live-data smoke test still returns 992/0.
     */
    private function clampAsOfS5(float $charge, float $min, float $max): float
    {
        if ($max <= 0) {
            return $charge;
        }

        return max($min, min($charge, $max));
    }

    /* ───────────────────────────────── fixtures ───────────────────────────────── */

    private function engine(): DeliveryChargeService
    {
        return app(DeliveryChargeService::class);
    }

    /**
     * `sub_self_delivery` is an ACCESSOR (Store::getSubSelfDeliveryAttribute), not a column:
     * for a store that is not on the subscription business model it returns
     * `self_delivery_system`. Setting the accessor's name via forceFill therefore does
     * nothing — the fixture has to drive the real column underneath it.
     */
    private function store(array $attributes): Store
    {
        $store = new Store;
        $store->forceFill($attributes + [
            'store_business_model' => 'commission',
            'self_delivery_system' => 0,
            'per_km_shipping_charge' => 0,
            'minimum_shipping_charge' => 0,
            'maximum_shipping_charge' => 0,
        ]);

        return $store;
    }

    private function pivot(string $type, float $perKm, float $min, float $max, float $fixed = 0.0): object
    {
        return (object) [
            'delivery_charge_type' => $type,
            'per_km_shipping_charge' => $perKm,
            'minimum_shipping_charge' => $min,
            'maximum_shipping_charge' => $max,
            'fixed_shipping_charge' => $fixed,
        ];
    }
}
