<?php

namespace Tests\Unit;

use App\Models\AdditionalDeliveryCharge;
use App\Models\EtaConfiguration;
use Carbon\Carbon;
use App\Models\Order;
use App\Models\Store;
use App\Services\Order\EtaService;
use App\Services\Zone\AdditionalDeliveryChargeService;
use App\Services\Zone\EtaConfigurationService;
use App\Services\Zone\ModuleZoneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * S7b — the estimate itself, port doc §11.2 to §11.5.
 *
 * The order is built in memory rather than saved: `forOrder()` reads the order and its store and
 * writes nothing, so saving one would only slow the suite down and leave rows behind.
 */
class EtaServiceTest extends TestCase
{
    use DatabaseTransactions;

    private EtaService $eta;

    private EtaConfigurationService $configs;

    private Store $store;

    private int $zoneId;

    private int $moduleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eta = app(EtaService::class);
        $this->configs = app(EtaConfigurationService::class);

        // Food specifically: the preparation buffer this file spends most of its assertions on
        // is now food-only (TC processing-time fix, 2026-09-14) — a kitchen is what the buffer
        // describes, and only Food has one. Any other module_type would silently drop the buffer
        // from every sum below and fail assertions that have nothing to do with that rule.
        $row = \DB::table('stores')
            ->join('modules', 'modules.id', '=', 'stores.module_id')
            ->whereNotNull('stores.zone_id')
            ->whereNotNull('stores.module_id')
            ->where('modules.module_type', 'food')
            ->select('stores.*')
            ->first();

        if (! $row) {
            $this->markTestSkipped('needs a Food-module store with a zone and a module');
        }

        $this->store = Store::withoutGlobalScopes()->find($row->id);
        $this->zoneId = (int) $row->zone_id;
        $this->moduleId = (int) $row->module_id;

        // The store's connected modules decide what a configuration may claim (D7).
        if (! in_array($this->moduleId, app(ModuleZoneService::class)->connectedModuleIds($this->zoneId), true)) {
            $this->markTestSkipped('the store\'s module is not connected to its zone');
        }

        // These cases describe a (zone, module) with NOTHING configured, so the pair has to
        // actually have nothing. Without this the suite passed only while no admin had set up an
        // ETA for the first store's pair, and broke the moment somebody legitimately did.
        // Safe to delete: DatabaseTransactions rolls the whole test back, live rows included.
        $this->clearConfigurationsForPair();
    }

    /** Detach and delete every ETA configuration covering the pair under test, inside the transaction. */
    private function clearConfigurationsForPair(): void
    {
        $ids = \DB::table('eta_configurations as e')
            ->join('eta_configuration_module as m', 'm.eta_configuration_id', '=', 'e.id')
            ->where('e.zone_id', $this->zoneId)
            ->where('m.module_id', $this->moduleId)
            ->pluck('e.id')
            ->all();

        if ($ids === []) {
            return;
        }

        \DB::table('eta_configuration_module')->whereIn('eta_configuration_id', $ids)->delete();
        \DB::table('eta_configurations')->whereIn('id', $ids)->delete();
        app(EtaConfigurationService::class)->forgetActive();
    }

    private function order(array $overrides = []): Order
    {
        $order = new Order;

        foreach (array_merge([
            'order_type' => 'delivery',
            'order_status' => 'pending',
            'zone_id' => $this->zoneId,
            'module_id' => $this->moduleId,
            'store_id' => $this->store->id,
            'delivery_type' => 'standard',
            'scheduled' => 0,
            'created_at' => now(),
        ], $overrides) as $key => $value) {
            $order->$key = $value;
        }

        return $order;
    }

    private function configure(array $overrides = []): EtaConfiguration
    {
        return $this->configs->create(array_merge([
            'name' => 'Test ETA',
            'zone_id' => $this->zoneId,
            'module_ids' => [$this->moduleId],
            'calculation_method' => EtaConfiguration::METHOD_DISTANCE,
            'minimum_delivery_time' => 20,
            'preparation_buffer' => 10,
            'transit_buffer' => 5,
            'time_gap' => 15,
        ], $overrides));
    }

    // ------------------------------------------------------------------ §11.3, the suffix is real

    public function test_minutes_are_read_as_minutes(): void
    {
        $this->assertSame([30, 40], $this->eta->parseDeliveryTime('30-40 min'));
        $this->assertSame([10, 30], $this->eta->parseDeliveryTime('10-30 minute'));
    }

    public function test_hours_are_multiplied_by_sixty(): void
    {
        $this->assertSame([120, 180], $this->eta->parseDeliveryTime('2-3 hours'));
    }

    public function test_days_are_multiplied_by_one_thousand_four_hundred_and_forty(): void
    {
        // mart's own addition — the live data has ecommerce stores quoting in days, which the
        // port source's parser would have read as three minutes.
        $this->assertSame([4320, 7200], $this->eta->parseDeliveryTime('3-5 days'));
    }

    public function test_a_range_typed_the_wrong_way_round_is_sorted(): void
    {
        // "20-16 min" and "122-23 min" are both in the live data.
        $this->assertSame([16, 20], $this->eta->parseDeliveryTime('20-16 min'));
    }

    public function test_an_unreadable_delivery_time_returns_nulls(): void
    {
        $this->assertSame([null, null], $this->eta->parseDeliveryTime(null));
        $this->assertSame([null, null], $this->eta->parseDeliveryTime('soon'));
    }

    // ------------------------------------------------------------------ §11.2, nothing configured

    public function test_a_zone_and_module_with_no_configuration_estimates_nothing(): void
    {
        $this->assertNull($this->eta->forOrder($this->order()));
    }

    public function test_a_configuration_that_is_switched_off_estimates_nothing(): void
    {
        $config = $this->configure();
        $this->configs->updateStatus($config->id, 0);

        $this->assertNull($this->eta->forOrder($this->order()));
    }

    // ------------------------------------------------------------------ §11.2, the arithmetic

    public function test_travel_time_arrives_in_seconds_and_is_converted_once(): void
    {
        $this->configure();

        // 600s = 10 min travel, + 10 preparation + 5 transit = 25, + 15 gap = 40.
        $eta = $this->eta->forOrder($this->order(['delivery_duration' => 600]));

        $this->assertSame(25, $eta['min']);
        $this->assertSame(40, $eta['max']);
    }

    public function test_the_minimum_is_a_floor_applied_before_the_gap_widens_it(): void
    {
        $this->configure();

        // 60s = 1 min travel, + 15 buffers = 16, under the floor of 20 -> 20, then + 15 gap.
        // Widening first and clamping after would give 20-31, a narrower range than configured.
        $eta = $this->eta->forOrder($this->order(['delivery_duration' => 60]));

        $this->assertSame(20, $eta['min']);
        $this->assertSame(35, $eta['max']);
    }

    public function test_the_floor_is_not_added_to_an_estimate_that_already_clears_it(): void
    {
        $this->configure(['minimum_delivery_time' => 5]);

        // 600s = 10 min, + 15 buffers = 25, over the floor. A term would give 30.
        $this->assertSame(25, $this->eta->forOrder($this->order(['delivery_duration' => 600]))['min']);
    }

    // ------------------------------------------------------------------ TC_39, the express saver shift

    public function test_an_express_order_shows_a_reduced_window_not_the_standard_one(): void
    {
        // The shared store this class's setUp() picks is self-delivery on this install, and
        // EtaService::saverShift() deliberately returns 0 for a self-delivery store (its
        // deliveries are never governed by the zone's own express/slightly-delay rule) -- exactly
        // like PlaceNewOrderTrait::resolveSaverDeliveryType() already does for the charge side.
        // So this needs its own non-self-delivery (store, zone, module) pair to actually exercise
        // the shift rather than trivially confirming the self-delivery bypass again.
        $row = \DB::table('stores')
            ->join('modules', 'modules.id', '=', 'stores.module_id')
            ->whereNotNull('stores.zone_id')->whereNotNull('stores.module_id')
            ->where('stores.self_delivery_system', 0)
            ->where('modules.module_type', 'food')
            ->select('stores.*')
            ->first();

        if (! $row) {
            $this->markTestSkipped('needs a non-self-delivery Food-module store with a zone and a module');
        }

        $store = Store::withoutGlobalScopes()->find($row->id);
        $zoneId = (int) $row->zone_id;
        $moduleId = (int) $row->module_id;

        if (! in_array($moduleId, app(ModuleZoneService::class)->connectedModuleIds($zoneId), true)) {
            $this->markTestSkipped('the store\'s module is not connected to its zone');
        }

        $ids = \DB::table('eta_configurations as e')
            ->join('eta_configuration_module as m', 'm.eta_configuration_id', '=', 'e.id')
            ->where('e.zone_id', $zoneId)->where('m.module_id', $moduleId)->pluck('e.id')->all();
        if ($ids !== []) {
            \DB::table('eta_configuration_module')->whereIn('eta_configuration_id', $ids)->delete();
            \DB::table('eta_configurations')->whereIn('id', $ids)->delete();
        }
        $this->configs->forgetActive();
        $this->configs->create([
            'name' => 'Test ETA', 'zone_id' => $zoneId, 'module_ids' => [$moduleId],
            'calculation_method' => EtaConfiguration::METHOD_DISTANCE,
            'minimum_delivery_time' => 20, 'preparation_buffer' => 10, 'transit_buffer' => 5, 'time_gap' => 15,
        ]);

        app(AdditionalDeliveryChargeService::class)->forgetActive();
        AdditionalDeliveryCharge::where('zone_id', $zoneId)->each(
            fn ($s) => app(AdditionalDeliveryChargeService::class)->delete($s->id)
        );
        app(AdditionalDeliveryChargeService::class)->create([
            'zone_id' => $zoneId,
            'module_ids' => [$moduleId],
            'vehicle_ids' => [],
            'express_extra_charge' => 5,
            'express_reduce_delivery_time' => 10,
            'express_reduce_delivery_time_unit' => 'min',
            'delay_reduce_charge' => 3,
            'delay_add_delivery_time' => 15,
            'delay_add_delivery_time_unit' => 'min',
        ]);

        $order = fn (array $overrides = []) => tap(new Order, function ($order) use ($store, $zoneId, $moduleId, $overrides) {
            foreach (array_merge([
                'order_type' => 'delivery', 'order_status' => 'pending',
                'zone_id' => $zoneId, 'module_id' => $moduleId, 'store_id' => $store->id,
                'delivery_type' => 'standard', 'scheduled' => 0, 'created_at' => now(),
            ], $overrides) as $key => $value) {
                $order->$key = $value;
            }
        });

        // 1200s = 20 min travel, + 10 preparation + 5 transit = 35, + 15 gap = 50 (standard).
        // Express shaves the configured 10 minutes off the subtotal before the gap is added:
        // 35-10=25, well clear of the 20-minute floor, so the full reduction is visible: 25-40.
        $standard = $this->eta->forOrder($order(['delivery_duration' => 1200]));
        $express = $this->eta->forOrder($order(['delivery_duration' => 1200, 'delivery_type' => 'express']));

        $this->assertSame([35, 50], [$standard['min'], $standard['max']]);
        $this->assertSame([25, 40], [$express['min'], $express['max']], 'an express order must show a reduced window, not the standard one');
        $this->assertGreaterThanOrEqual(0, $express['min'], 'no wrong (negative) time may be shown to the store');
    }

    public function test_the_store_writing_its_processing_time_replaces_the_preparation_buffer(): void
    {
        $this->configure();

        $eta = $this->eta->forOrder($this->order([
            'delivery_duration' => 600, 'order_status' => 'processing', 'processing_time' => 25,
            'processing' => now(),
        ]));

        // 10 travel + 25 processing (not the 10 buffer) + 5 transit = 40.
        $this->assertSame(40, $eta['min']);
        $this->assertSame('processing', $eta['stage']);
    }

    public function test_a_status_that_ran_ahead_of_the_processing_time_keeps_the_buffer(): void
    {
        $this->configure();

        $eta = $this->eta->forOrder($this->order([
            'delivery_duration' => 600, 'order_status' => 'processing', 'processing_time' => 0,
        ]));

        // The buffer stands rather than a zero being added: 10 + 10 + 5.
        $this->assertSame(25, $eta['min']);
        $this->assertSame('before_processing', $eta['stage']);
    }

    /**
     * Owner decision 2026-09-15: a Distance Based order with no travel time no longer borrows
     * the store's own delivery-time range (that number belongs to Fixed Delivery Time alone,
     * covered separately below) — it falls back to the floor plus the buffers, widened by the
     * same gap a real quote would use, so the estimate stays the range the admin actually
     * configured rather than one shaped by whatever the store happens to have typed.
     */
    public function test_a_distance_based_order_with_no_travel_time_falls_back_to_the_floor(): void
    {
        $config = $this->configure();

        $eta = $this->eta->forOrder($this->order(['delivery_duration' => null]));

        $floor = max($config->preparation_buffer + $config->transit_buffer, $config->minimum_delivery_time);
        $this->assertSame($floor, $eta['min']);
        $this->assertSame($floor + $config->time_gap, $eta['max']);
    }

    // ------------------------------------------------------------------ §11.4, the freeze

    public function test_the_snapshot_captures_configuration_and_not_the_order(): void
    {
        $this->configure();

        $snapshot = $this->eta->snapshotFor($this->order());

        $this->assertSame(EtaConfiguration::METHOD_DISTANCE, $snapshot['calculation_method']);
        $this->assertSame(20, $snapshot['minimum_delivery_time']);
        $this->assertSame($this->store->delivery_time, $snapshot['store_delivery_time']);
        $this->assertArrayNotHasKey('delivery_duration', $snapshot);
    }

    public function test_there_is_no_snapshot_where_there_is_no_configuration(): void
    {
        $this->assertNull($this->eta->snapshotFor($this->order()));
    }

    public function test_a_snapshotted_order_ignores_a_later_edit_to_the_configuration(): void
    {
        $config = $this->configure();
        $order = $this->order(['delivery_duration' => 600]);
        $order->eta_snapshot = $this->eta->snapshotFor($order);

        $before = $this->eta->forOrder($order);

        $this->configs->update($config->id, [
            'zone_id' => $this->zoneId,
            'module_ids' => [$this->moduleId],
            'calculation_method' => EtaConfiguration::METHOD_DISTANCE,
            'minimum_delivery_time' => 500,
            'preparation_buffer' => 200,
            'transit_buffer' => 200,
            'time_gap' => 0,
        ]);

        $this->assertSame($before['min'], $this->eta->forOrder($order)['min']);
    }

    public function test_an_order_without_a_snapshot_follows_the_edited_configuration(): void
    {
        $config = $this->configure();
        $order = $this->order(['delivery_duration' => 600]);

        $this->assertSame(25, $this->eta->forOrder($order)['min']);

        $this->configs->update($config->id, [
            'zone_id' => $this->zoneId,
            'module_ids' => [$this->moduleId],
            'calculation_method' => EtaConfiguration::METHOD_DISTANCE,
            'minimum_delivery_time' => 500,
            'preparation_buffer' => 200,
            'transit_buffer' => 200,
            'time_gap' => 0,
        ]);

        $this->assertSame(500, $this->eta->forOrder($order)['min']);
    }

    // ------------------------------------------------------------------ §11.5, presentation

    public function test_nothing_is_estimated_for_a_finished_order(): void
    {
        $this->configure();

        foreach (EtaService::FINISHED as $status) {
            $this->assertNull(
                $this->eta->forOrder($this->order(['order_status' => $status, 'delivery_duration' => 600])),
                $status.' should not be estimated',
            );
        }
    }

    public function test_nothing_is_estimated_for_a_take_away_order(): void
    {
        $this->configure();

        $this->assertNull($this->eta->forOrder($this->order(['order_type' => 'take_away'])));
    }

    /**
     * Parcel gained its own ETA setup (owner decision 2026-09-15) and is no longer refused on
     * sight the way take_away still is — it reads the general config here only because this
     * order's module has no parcel-specific columns set; a real Parcel module reads its own
     * three instead, covered by the effectiveTimings()-specific tests below.
     */
    public function test_a_parcel_order_is_now_estimated(): void
    {
        $this->configure();

        $this->assertNotNull($this->eta->forOrder($this->order(['order_type' => 'parcel'])));
    }

    /**
     * StackFood's overdue rule, adopted by owner decision 2026-09-09: the near end stands — it is
     * what the customer was promised — and only the far end moves to a few minutes from now.
     *
     * This replaced a version that moved BOTH ends to now and reported "any minute now". The
     * consequence is deliberate and accepted: `max` is restated from the anchor, so a long-stale
     * order reports the whole elapsed span rather than the minutes left.
     */
    public function test_an_order_past_its_window_keeps_its_near_end_and_moves_only_the_far_end(): void
    {
        $this->configure();

        $order = $this->order(['delivery_duration' => 600, 'created_at' => now()->subHours(3)]);
        $eta = $this->eta->forOrder($order);

        $this->assertTrue($eta['overdue']);

        // The promise is untouched.
        $unpaid = $this->eta->forOrder($this->order(['delivery_duration' => 600, 'created_at' => now()]));
        $this->assertSame($unpaid['min'], $eta['min'], 'the near end must not move when an order runs late');

        // The far end sits OVERDUE_MINUTES from now, restated from the anchor.
        $this->assertGreaterThan($eta['min'], $eta['max']);
        // A few minutes from now, not a re-quote of the whole wait.
        $this->assertLessThanOrEqual(
            10,
            abs(Carbon::parse($eta['to_at'])->diffInMinutes(Carbon::now())),
            'the far end closes a few minutes from now',
        );

        // A range either way — never replaced by a phrase.
        $this->assertNotSame(translate('messages.eta_any_minute_now'), $eta['text']);
    }

    public function test_a_long_range_is_written_in_hours_or_days(): void
    {
        $this->configure(['calculation_method' => EtaConfiguration::METHOD_FIXED, 'preparation_buffer' => 0, 'transit_buffer' => 0]);

        $order = $this->order();
        $order->eta_snapshot = [
            'calculation_method' => EtaConfiguration::METHOD_FIXED,
            'minimum_delivery_time' => 0, 'preparation_buffer' => 0, 'transit_buffer' => 0,
            'time_gap' => 0, 'store_delivery_time' => '3-5 days', 'saver_shift' => 0,
        ];

        $eta = $this->eta->forOrder($order);

        // min and max stay in MINUTES whatever the text reads.
        $this->assertSame(4320, $eta['min']);
        $this->assertSame(7200, $eta['max']);
        $this->assertStringContainsString('3 - 5', $eta['text']);
        $this->assertStringNotContainsString('4320', $eta['text']);
    }

    // ------------------------------------------------------------------ attaching to screens

    public function test_attach_fills_the_estimate_on_a_single_order(): void
    {
        $this->configure();
        $order = $this->order(['delivery_duration' => 600]);

        $this->assertNull($order->eta);
        $this->eta->attach($order);
        $this->assertSame(25, $order->eta['min']);
    }

    public function test_attach_fills_every_order_in_a_collection(): void
    {
        $this->configure();
        $orders = collect([
            $this->order(['delivery_duration' => 600]),
            $this->order(['delivery_duration' => 1200]),
        ]);

        $this->eta->attach($orders);

        $this->assertSame(25, $orders[0]->eta['min']);
        $this->assertSame(35, $orders[1]->eta['min']);
    }

    public function test_attach_leaves_null_where_there_is_nothing_to_estimate(): void
    {
        $this->configure();
        $order = $this->order(['order_status' => 'delivered']);

        $this->eta->attach($order);

        $this->assertNull($order->eta);
    }

    public function test_the_panel_window_is_null_when_there_is_no_estimate(): void
    {
        $this->assertNull($this->eta->panelWindow(null));
    }

    public function test_the_panel_window_drops_the_closing_date_when_it_is_the_same_day(): void
    {
        $this->configure();
        $eta = $this->eta->forOrder($this->order(['delivery_duration' => 600]));

        // "02 Sep 2026 05:02 PM - 05:17 PM" — one date, two clock times.
        $this->assertSame(1, substr_count($this->eta->panelWindow($eta), date('d M Y')));
    }

    public function test_the_payload_carries_every_documented_key(): void
    {
        $this->configure();

        $eta = $this->eta->forOrder($this->order(['delivery_duration' => 600]));

        foreach (['min', 'max', 'unit', 'text', 'overdue', 'from', 'to', 'window', 'from_at', 'to_at', 'timezone', 'stage'] as $key) {
            $this->assertArrayHasKey($key, $eta);
        }
    }

    // ------------------------------------------------------------------ processing-time / module scope

    /** A non-food (store, zone, module) with an ETA Configuration attached, or a skipped test. */
    private function nonFoodPair(string $configName): ?array
    {
        $row = \DB::table('stores')
            ->join('modules', 'modules.id', '=', 'stores.module_id')
            ->whereNotNull('stores.zone_id')->whereNotNull('stores.module_id')
            ->where('modules.module_type', '!=', 'food')
            ->select('stores.*')
            ->first();

        if (! $row) {
            $this->markTestSkipped('needs a non-Food-module store with a zone and a module');

            return null;
        }

        $zoneId = (int) $row->zone_id;
        $moduleId = (int) $row->module_id;

        if (! in_array($moduleId, app(ModuleZoneService::class)->connectedModuleIds($zoneId), true)) {
            $this->markTestSkipped('the store\'s module is not connected to its zone');

            return null;
        }

        $ids = \DB::table('eta_configurations as e')
            ->join('eta_configuration_module as m', 'm.eta_configuration_id', '=', 'e.id')
            ->where('e.zone_id', $zoneId)->where('m.module_id', $moduleId)->pluck('e.id')->all();
        if ($ids !== []) {
            \DB::table('eta_configuration_module')->whereIn('eta_configuration_id', $ids)->delete();
            \DB::table('eta_configurations')->whereIn('id', $ids)->delete();
        }
        $this->configs->forgetActive();
        $this->configs->create([
            'name' => $configName, 'zone_id' => $zoneId, 'module_ids' => [$moduleId],
            'calculation_method' => EtaConfiguration::METHOD_DISTANCE,
            'minimum_delivery_time' => 20, 'preparation_buffer' => 10, 'transit_buffer' => 5, 'time_gap' => 15,
        ]);

        return ['store_id' => (int) $row->id, 'zone_id' => $zoneId, 'module_id' => $moduleId];
    }

    /**
     * Only Food has a kitchen for the preparation buffer to describe. Confirmed against a real
     * defect: one ETA Configuration attached to both Food and Grocery let its single
     * preparation_buffer leak into every Grocery order too, because nothing read which module
     * the order it was pricing actually belonged to (processing-time fix, 2026-09-14).
     */
    public function test_a_non_food_orders_estimate_ignores_the_preparation_buffer(): void
    {
        $pair = $this->nonFoodPair('Test ETA (non-food)');
        if (! $pair) {
            return;
        }

        $order = new Order;
        foreach (array_merge($pair, [
            'order_type' => 'delivery', 'order_status' => 'pending',
            'delivery_type' => 'standard', 'scheduled' => 0, 'created_at' => now(),
            'delivery_duration' => 1200,
        ]) as $key => $value) {
            $order->$key = $value;
        }

        // 1200s = 20 min travel, + 0 preparation (non-food) + 5 transit = 25, + 15 gap = 40.
        // A Food order under the identical configuration reads 35-50 (see the express test above,
        // same buffers) — the 10-minute preparation buffer is the entire difference.
        $eta = $this->eta->forOrder($order);

        $this->assertSame([25, 40], [$eta['min'], $eta['max']], 'a non-food order must not carry the preparation buffer');
    }

    /** The frozen snapshot must agree with the live rule, or replaying an old order would drift. */
    public function test_a_non_food_orders_snapshot_freezes_a_zero_preparation_buffer(): void
    {
        $pair = $this->nonFoodPair('Test ETA (non-food snapshot)');
        if (! $pair) {
            return;
        }

        $order = new Order;
        foreach (array_merge($pair, [
            'order_type' => 'delivery', 'order_status' => 'pending',
        ]) as $key => $value) {
            $order->$key = $value;
        }

        $this->assertSame(0, $this->eta->snapshotFor($order)['preparation_buffer']);
    }

    // ------------------------------------------------------------------ Parcel's own numbers

    /** Same shape as nonFoodPair(), but for a Parcel-module store, with parcel's own three set. */
    private function parcelPair(string $configName): ?array
    {
        $row = \DB::table('stores')
            ->join('modules', 'modules.id', '=', 'stores.module_id')
            ->whereNotNull('stores.zone_id')->whereNotNull('stores.module_id')
            ->where('modules.module_type', 'parcel')
            ->select('stores.*')
            ->first();

        if (! $row) {
            $this->markTestSkipped('needs a Parcel-module store with a zone and a module');

            return null;
        }

        $zoneId = (int) $row->zone_id;
        $moduleId = (int) $row->module_id;

        if (! in_array($moduleId, app(ModuleZoneService::class)->connectedModuleIds($zoneId), true)) {
            $this->markTestSkipped('the store\'s module is not connected to its zone');

            return null;
        }

        $ids = \DB::table('eta_configurations as e')
            ->join('eta_configuration_module as m', 'm.eta_configuration_id', '=', 'e.id')
            ->where('e.zone_id', $zoneId)->where('m.module_id', $moduleId)->pluck('e.id')->all();
        if ($ids !== []) {
            \DB::table('eta_configuration_module')->whereIn('eta_configuration_id', $ids)->delete();
            \DB::table('eta_configurations')->whereIn('id', $ids)->delete();
        }
        $this->configs->forgetActive();
        // General set deliberately different from Parcel's own, so a test reading the wrong one
        // would fail loudly rather than by coincidence.
        $this->configs->create([
            'name' => $configName, 'zone_id' => $zoneId, 'module_ids' => [$moduleId],
            'calculation_method' => EtaConfiguration::METHOD_FIXED,
            'minimum_delivery_time' => 999, 'preparation_buffer' => 999, 'transit_buffer' => 999, 'time_gap' => null,
            'parcel_minimum_delivery_time' => 20, 'parcel_transit_buffer' => 5, 'parcel_time_gap' => 15,
        ]);

        return ['store_id' => (int) $row->id, 'zone_id' => $zoneId, 'module_id' => $moduleId];
    }

    /**
     * Parcel reads its own three columns, never the general four — proven by configuring the
     * general set to numbers that would produce an obviously wrong answer (999-anything) if
     * effectiveTimings() ever picked them for a Parcel order by mistake.
     */
    public function test_a_parcel_order_reads_its_own_columns_not_the_general_ones(): void
    {
        $pair = $this->parcelPair('Test ETA (parcel)');
        if (! $pair) {
            return;
        }

        $order = new Order;
        foreach (array_merge($pair, [
            'order_type' => 'delivery', 'order_status' => 'pending',
            'delivery_type' => 'standard', 'scheduled' => 0, 'created_at' => now(),
            'delivery_duration' => null,
        ]) as $key => $value) {
            $order->$key = $value;
        }

        // No travel time and no prep buffer (parcel has none): min = max(0 + 5, 20) = 20, gap 15.
        $eta = $this->eta->forOrder($order);

        $this->assertSame(20, $eta['min']);
        $this->assertSame(35, $eta['max']);
    }

    /** The frozen snapshot for a Parcel order carries the already-resolved parcel numbers. */
    public function test_a_parcel_orders_snapshot_freezes_its_own_numbers(): void
    {
        $pair = $this->parcelPair('Test ETA (parcel snapshot)');
        if (! $pair) {
            return;
        }

        $order = new Order;
        foreach (array_merge($pair, [
            'order_type' => 'delivery', 'order_status' => 'pending',
        ]) as $key => $value) {
            $order->$key = $value;
        }

        $snapshot = $this->eta->snapshotFor($order);

        $this->assertSame(EtaConfiguration::METHOD_DISTANCE, $snapshot['calculation_method']);
        $this->assertSame(20, $snapshot['minimum_delivery_time']);
        $this->assertSame(5, $snapshot['transit_buffer']);
        $this->assertSame(15, $snapshot['time_gap']);
        $this->assertSame(0, $snapshot['preparation_buffer']);
    }
}
