<?php

namespace App\Services\Order;

use App\Models\EtaConfiguration;
use App\Models\Order;
use App\Services\BaseService;
use App\Services\Zone\EtaConfigurationService;
use App\Services\Zone\ModuleZoneDeliveryOptionService;
use Carbon\Carbon;

/**
 * The estimated delivery time a customer is shown — port doc §11.2 to §11.5.
 *
 * One place, so the app, the web frontend and anything added later cannot drift apart.
 *
 * **Everything here is minutes.** The only figure that arrives in another unit is the order's
 * `delivery_duration`, which the app takes from the map API in seconds and which is converted
 * once, on the way in.
 *
 * The estimate is travel time plus the two buffers. The configuration's `calculation_method`
 * decides only WHERE the travel time is read from — the map, or the store's own delivery time.
 * Before the kitchen starts, the preparation buffer stands in for how long the store will take;
 * once the store has written `processing_time`, that figure replaces the buffer.
 *
 * **Nothing is estimated for a (zone, module) whose admin has not set one up.** Silence is
 * correct where a made-up number is not: an ETA is a promise, and the platform never agreed to
 * one it was not configured to make.
 *
 * S19 makes that path unreachable for a NEW order and leaves it in place for an old one. A module
 * with no active ETA configuration is now unavailable in the zone — the customer is not offered
 * it, and place_order refuses it — so nothing live should arrive here without one. Historical
 * orders are the reason the null path stays: an order placed before the setup was removed, or
 * before the rule existed, is re-read from `Order::eta_snapshot` and must not start throwing
 * because its configuration has since gone. Silence, still, rather than a number nobody promised.
 */
class EtaService extends BaseService
{
    /** Statuses past which there is nothing left to estimate (§11.5). */
    public const FINISHED = [
        'delivered', 'canceled', 'failed', 'refunded', 'refund_requested', 'refund_request_canceled',
    ];

    /** Statuses at or beyond the store starting work. */
    private const PROCESSED = ['processing', 'handover', 'picked_up'];

    /**
     * How far ahead of now an overdue order's window is closed off, in minutes.
     *
     * Short on purpose: past its window the order is on its way rather than being estimated
     * afresh, and the only honest thing left to say is "any minute now".
     */
    private const OVERDUE_MINUTES = 5;

    /** Minutes in an hour and in a day, for reading a store's delivery time and writing a label. */
    private const HOUR = 60;

    private const DAY = 1440;

    /**
     * The whole `eta` object for one order, or null when none can be made.
     *
     * @return array{min:int,max:int,unit:string,text:string,overdue:bool,from:string,to:string,window:string,from_at:string,to_at:string,timezone:string,stage:string}|null
     */
    public function forOrder(Order $order): ?array
    {
        // Parcel admitted alongside delivery: it has its own ETA setup now (see
        // effectiveTimings()), so it is no longer the one order_type this method refuses on sight.
        if (! in_array($order->order_type, ['delivery', 'parcel'], true) || in_array($order->order_status, self::FINISHED, true)) {
            return null;
        }

        // The inputs this order was quoted against, if they were frozen at placement (§11.4).
        // Orders placed before the freeze shipped have none and keep reading the live setup.
        $frozen = is_array($order->eta_snapshot) && $order->eta_snapshot !== [] ? $order->eta_snapshot : null;

        $config = $frozen
            ? $this->hydrate($frozen)
            : app(EtaConfigurationService::class)->activeConfig([$order->zone_id], $order->module_id);

        if (! $config) {
            return null;
        }

        // The store's own figure is trusted only once it has been written; a status that ran
        // ahead of the write keeps the buffer rather than adding a zero.
        $processing = in_array($order->order_status, self::PROCESSED, true) && (int) $order->processing_time > 0;

        // Only Food has a kitchen for a preparation buffer to describe — a Grocery, Pharmacy or
        // Parcel order has nothing to cook, so neither the configured buffer nor a written
        // processing_time may count toward its estimate. One ETA Configuration row can cover
        // several modules at once (Food alongside Grocery, say), and its single
        // preparation_buffer field was leaking into every one of them; this is where that stops.
        $head = $order->module?->module_type === 'food'
            ? ($processing ? (int) $order->processing_time : (int) $config->preparation_buffer)
            : 0;

        [$floor, $transit, $gap, $method] = $this->effectiveTimings($config, $order);

        $range = $this->range($order, $method, $floor, $transit, $gap, $head, $frozen);

        if (! $range) {
            return null;
        }

        return $this->present($order, $range[0], $range[1], $processing ? 'processing' : 'before_processing');
    }

    /**
     * Which four numbers this order's estimate is actually built from.
     *
     * Parcel reads its own three columns and is always treated as distance-based — there is no
     * store delivery-time range for a pickup point to fall back to, and no fixed-method
     * alternative was ever offered for it on the form. Every other module keeps reading the
     * general four columns exactly as before.
     *
     * A frozen (hydrated) config never has the parcel columns set — hydrate() only ever restores
     * the general four keys, because snapshotFor() already resolved and froze the RIGHT set under
     * those same keys at placement time. So this falls through to the general branch for a frozen
     * parcel order too, and correctly reads back whatever was frozen for it.
     *
     * @return array{0:int,1:int,2:int,3:string} floor, transit, gap, method
     */
    private function effectiveTimings(EtaConfiguration $config, Order $order): array
    {
        $isParcel = $order->module?->module_type === 'parcel';

        if ($isParcel && $config->parcel_minimum_delivery_time !== null) {
            return [
                (int) $config->parcel_minimum_delivery_time,
                (int) $config->parcel_transit_buffer,
                (int) $config->parcel_time_gap,
                EtaConfiguration::METHOD_DISTANCE,
            ];
        }

        return [
            (int) $config->minimum_delivery_time,
            (int) $config->transit_buffer,
            (int) $config->time_gap,
            (string) $config->calculation_method,
        ];
    }

    /**
     * Fill `Order::$eta` for one order, a collection, or a paginator.
     *
     * The surfaces that show an ETA all reach it through here rather than calling `forOrder()`
     * from inside a resource, because a resource never queries (rule 3) — and a list would ask
     * the same configuration question once per row. `EtaConfigurationService` memoises that
     * lookup, so a page of orders costs one query per distinct (zone, module) rather than one
     * per order (rule 11).
     */
    public function attach(mixed $orders): mixed
    {
        if ($orders instanceof Order) {
            $orders->eta = $this->forOrder($orders);

            return $orders;
        }

        $rows = $orders instanceof \Illuminate\Contracts\Pagination\Paginator
            || $orders instanceof \Illuminate\Pagination\AbstractPaginator
            ? $orders->getCollection()
            : $orders;

        foreach ($rows ?? [] as $order) {
            if ($order instanceof Order) {
                $order->eta = $this->forOrder($order);
            }
        }

        return $orders;
    }

    /**
     * A store's `delivery_time` as [min, max] minutes — §11.3.
     *
     * Stored as `30-40 min`, `2-3 hours` or `3-5 days`, and **the unit suffix is real**. Readers
     * elsewhere in the panels drop it, so an hours store reads as minutes to them. Here it does
     * not. `days` is mart's own addition: the live data has ecommerce stores quoting `3-5 days`,
     * which the StackFood parser would have read as three minutes.
     *
     * The pair is sorted, because the live data also holds `20-16 min` — typed the wrong way
     * round, and a range whose ends are reversed would otherwise produce a maximum below its
     * minimum.
     *
     * @return array{0:?int,1:?int}
     */
    public function parseDeliveryTime(?string $value): array
    {
        if (! $value || ! preg_match('/(\d+(?:\.\d+)?)\D+(\d+(?:\.\d+)?)/', $value, $matches)) {
            return [null, null];
        }

        $lower = strtolower($value);

        $factor = match (true) {
            str_contains($lower, 'day') => self::DAY,
            str_contains($lower, 'hour') => self::HOUR,
            default => 1,
        };

        $from = (int) round($matches[1] * $factor);
        $to = (int) round($matches[2] * $factor);

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    /**
     * The ETA inputs to freeze onto an order at placement (§11.4), or null when its (zone, module)
     * has no active configuration and there is nothing to quote against.
     *
     * **Only configuration is captured.** Everything belonging to the order itself — its travel
     * duration, its processing time, its stage — stays live, so the estimate still narrows as the
     * store accepts and prepares it. What stops moving is the effect of an admin editing the ETA
     * setup, the saver delivery options, or the store's delivery time after the fact.
     */
    public function snapshotFor(Order $order): ?array
    {
        $config = app(EtaConfigurationService::class)->activeConfig([$order->zone_id], $order->module_id);

        if (! $config) {
            return null;
        }

        // Resolved to whichever set applies to this order — Parcel's own three, or the general
        // four — and frozen under the SAME keys either way. hydrate() only ever knows the general
        // names, so a replay of a frozen Parcel order must find its numbers there, already
        // resolved; it must not need to remember it was Parcel at all.
        [$floor, $transit, $gap, $method] = $this->effectiveTimings($config, $order);

        return [
            'calculation_method' => $method,
            'minimum_delivery_time' => $floor,
            // Frozen honestly: a non-food order never used the buffer live (see forOrder()), so
            // the snapshot must not remember one either, or a replay would disagree with itself.
            'preparation_buffer' => $order->module?->module_type === 'food' ? (int) $config->preparation_buffer : 0,
            'transit_buffer' => $transit,
            'time_gap' => $gap,
            'store_delivery_time' => $order->store?->delivery_time,
            'saver_shift' => $this->saverShift($order),
        ];
    }

    /**
     * The clock window as the panels print it: "02 Sep 2026 04:37 PM - 04:52 PM".
     *
     * Built here rather than in a Blade (rule 8). The closing time carries its date only when the
     * window ends on a later day than it opened — an overdue window can — because a bare clock
     * time then reads as though it finished before it started.
     */
    public function panelWindow(?array $eta): ?string
    {
        if (! $eta) {
            return null;
        }

        $format = 'd M Y '.config('timeformat');
        $from = strtotime($eta['from_at']);
        $to = strtotime($eta['to_at']);

        if ($from === $to) {
            return date($format, $from);
        }

        $sameDay = date('Y-m-d', $from) === date('Y-m-d', $to);

        return date($format, $from).' - '.date($sameDay ? config('timeformat') : $format, $to);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * The range in minutes.
     *
     * Takes the already-resolved floor/transit/gap/method rather than an EtaConfiguration
     * directly — {@see effectiveTimings()} is what decides whether those four numbers are the
     * general set or Parcel's own, so this method never has to know which module it's estimating
     * for; it just runs the formula the method name says to run.
     *
     * §11.2 — the floor is applied to the subtotal **before** the gap widens it. The gap is how
     * far apart the two ends of the range sit, so clamping afterwards would hand back a range
     * narrower than the zone asked for.
     *
     * @return array{0:int,1:int}|null
     */
    private function range(Order $order, string $method, int $floor, int $transit, int $gap, int $head, ?array $frozen): ?array
    {
        if ($method === EtaConfiguration::METHOD_DISTANCE) {
            if (is_numeric($order->delivery_duration)) {
                $subtotal = $this->minutes($order->delivery_duration) + $head + $transit;
                $min = max($this->saverAdjusted($order, $subtotal, $frozen), $floor);

                return [$min, $min + $gap];
            }

            // The map never returned a travel time for this order — the client didn't send
            // one, or the Routes API call failed. Distance Based was configured to read travel
            // time from the map, never from the store's own delivery time, so borrowing that
            // range here would answer with a number this method was never set up to give. The
            // floor plus the buffers, widened by the same gap a real quote would use, keeps the
            // promise the admin actually configured instead. Parcel always lands here too, on
            // the same reasoning — it has no store delivery-time range to borrow in the first
            // place, only a map-based estimate or this floor.
            $min = max($this->saverAdjusted($order, $head + $transit, $frozen), $floor);

            return [$min, $min + $gap];
        }

        // Fixed Delivery Time: the store's own range IS the method, not a fallback for a missing
        // one. (Parcel never reaches here — effectiveTimings() always resolves it to
        // METHOD_DISTANCE, since the form never offered it a fixed-method alternative.)
        //
        // The store's figure as it stood when the order was placed: it is a configuration value
        // like any other here, so an admin editing it must not move an in-flight estimate.
        [$storeMin, $storeMax] = $this->parseDeliveryTime(
            $frozen ? ($frozen['store_delivery_time'] ?? null) : $order->store?->delivery_time,
        );

        // A store with no usable delivery_time still has to produce an estimate — returning null
        // here would surface as a blank ETA to the customer. The setup's own minimum is the
        // sensible floor to fall back on, and its gap gives that a range; with neither set, 30
        // and 10 keep the answer from being blank or zero.
        if ($storeMin === null) {
            $fallback = $floor > 0 ? $floor : 30;
            $storeMin = $fallback;
            $storeMax = $fallback + ($gap > 0 ? $gap : 10);
            $head = 0;
            $transit = 0;
        }

        $min = max($this->saverAdjusted($order, $storeMin + $head + $transit, $frozen), $floor);
        $max = max($this->saverAdjusted($order, $storeMax + $head + $transit, $frozen), $min);

        return [$min, $max];
    }

    /** Seconds to minutes. Half a minute of travel is still a minute of waiting. */
    private function minutes(mixed $seconds): int
    {
        return (int) round(((int) $seconds) / 60);
    }

    /**
     * Express and slightly delayed delivery move the estimate.
     *
     * The customer paid for the faster one, so the time they are shown moves with it. Self
     * delivering stores are left alone, exactly as the saver delivery CHARGE leaves them alone.
     */
    private function saverAdjusted(Order $order, int $minutes, ?array $frozen): int
    {
        $shift = $frozen !== null ? (int) ($frozen['saver_shift'] ?? 0) : $this->saverShift($order);

        return max(0, $minutes + $shift);
    }

    /**
     * How far the order's saver choice moves the estimate, in signed minutes.
     *
     * Negative for express, positive for slightly delayed, zero otherwise. Read from the live
     * option, which is what {@see snapshotFor()} captures at placement — afterwards the frozen
     * figure is used and editing the option no longer moves the order.
     *
     * mart keys these on (module, zone) rather than zone alone, so the lookup passes both.
     */
    private function saverShift(Order $order): int
    {
        $type = (string) ($order->delivery_type ?? '');

        if ($type === '' || $order->store?->sub_self_delivery == 1) {
            return 0;
        }

        if ($type !== 'express' && $type !== 'slightly_delay') {
            return 0;
        }

        $option = app(ModuleZoneDeliveryOptionService::class)
            ->findForModuleZoneType($order->module_id, $order->zone_id, $type);

        if (! $option) {
            return 0;
        }

        // The model's accessors hand these back as a {value, unit, minutes} triple, so the
        // minutes are already worked out and nothing here re-derives them.
        return $type === 'express'
            ? -(int) ($option->reduce_delivery_time['minutes'] ?? 0)
            : (int) ($option->add_delivery_time['minutes'] ?? 0);
    }

    /** A configuration object built from a frozen snapshot; never saved. */
    private function hydrate(array $frozen): EtaConfiguration
    {
        $config = new EtaConfiguration;

        $config->calculation_method = $frozen['calculation_method'] ?? EtaConfiguration::METHOD_FIXED;
        $config->minimum_delivery_time = (int) ($frozen['minimum_delivery_time'] ?? 0);
        $config->preparation_buffer = (int) ($frozen['preparation_buffer'] ?? 0);
        $config->transit_buffer = (int) ($frozen['transit_buffer'] ?? 0);
        $config->time_gap = (int) ($frozen['time_gap'] ?? 0);

        return $config;
    }

    /**
     * The range, said both ways: minutes, and the clock window it lands on (§11.5).
     *
     * @return array{min:int,max:int,unit:string,text:string,overdue:bool,from:string,to:string,window:string,from_at:string,to_at:string,timezone:string,stage:string}
     */
    private function present(Order $order, int $min, int $max, string $stage): array
    {
        $anchor = $this->anchor($order, $stage);
        $from = $anchor->copy()->addMinutes($min);
        $to = $anchor->copy()->addMinutes($max);

        // Once the far end of the window has gone by the order is overdue. StackFood's rule, and
        // ours by owner decision 2026-09-09: the NEAR end stands — it is what the customer was
        // promised and nothing about it has changed — while the far end moves to a few minutes
        // from now, so a customer refreshing an hour late reads minutes rather than being quoted
        // the whole wait a second time.
        //
        // The two stay in order without a guard: overdue means now is past anchor + max, which is
        // at or past anchor + min, so the new far end is always the later of the pair.
        //
        // `isPast()` compares the whole timestamp, so a window that closed yesterday evening is
        // overdue this morning; only the clock labels drop the date, and from_at/to_at keep it.
        //
        // KNOWN CONSEQUENCE, accepted with the decision: `max` is restated from the ANCHOR, so a
        // long-stale order reports the whole elapsed span — an order six weeks past its slot
        // reads "1 - 1008 hr" over a window that appears to end before it begins, because the
        // labels drop the date. `overdue` is the flag to branch on rather than the numbers.
        $overdue = $to->isPast();

        if ($overdue) {
            $to = Carbon::now()->addMinutes(self::OVERDUE_MINUTES);
            // Cast because Carbon 3 hands back a float where Carbon 2 gave an int, and `max` is
            // published as whole minutes.
            $max = (int) round($anchor->diffInMinutes($to));
        }

        // Same setting panelWindow() reads for the admin/vendor Blade views -- the API's own
        // from/to/window labels were hardcoded to 12-hour independently of it, so a 24-hour shop
        // saw its panel switch while these API fields stayed stuck in AM/PM.
        $fromLabel = $from->format(config('timeformat'));
        $toLabel = $to->format(config('timeformat'));

        return [
            // Always MINUTES, whatever `text` reads — so a client doing its own arithmetic has
            // one unit to work in and `unit` always describes these two numbers.
            'min' => $min,
            'max' => $max,
            'unit' => translate('ETA minute unit'),
            // The range, overdue or not — StackFood states the span either way rather than
            // replacing it with a phrase. `overdue` below is what a client branches on.
            'text' => $this->text($min, $max),
            // Additive, and the reason `text` stops carrying numbers: a client that wants to
            // style a late order differently would otherwise have to infer it from the window.
            'overdue' => $overdue,
            'from' => $fromLabel,
            'to' => $toLabel,
            'window' => $fromLabel === $toLabel ? $fromLabel : "{$fromLabel} - {$toLabel}",
            'from_at' => $from->toIso8601String(),
            'to_at' => $to->toIso8601String(),
            // The panel's own timezone: ConfigServiceProvider makes it PHP's default, which is
            // what every order timestamp is written in. config('app.timezone') is still UTC.
            'timezone' => date_default_timezone_get(),
            'stage' => $stage,
        ];
    }

    private function text(int $min, int $max): string
    {
        [$scale, $unit] = match (true) {
            $min >= self::DAY => [self::DAY, translate('ETA day unit')],
            $min >= self::HOUR => [self::HOUR, translate('ETA hour unit')],
            default => [1, translate('ETA minute unit')],
        };

        $low = (int) round($min / $scale);
        $high = (int) round($max / $scale);

        return $low === $high ? "{$low} {$unit}" : "{$low} - {$high} {$unit}";
    }

    /**
     * What the window is measured from.
     *
     * The stage's own moment rather than now, so the window holds still and the wait visibly
     * shrinks; it moves once, when the store starts and the estimate is genuinely made again.
     *
     * A scheduled order is measured from the slot it was booked for, at every stage including
     * once work has begun — a store may start an order due tonight early in the afternoon, and
     * anchoring on the moment it began would promise the goods hours before the customer asked
     * for them.
     *
     * StackFood also anchors repeat orders on their next subscription occurrence, roughly 200
     * lines of schedule arithmetic. mart has no subscription orders — no `orders.subscription_id`
     * and no `subscriptions` table — so none of that is ported.
     */
    private function anchor(Order $order, string $stage): Carbon
    {
        $booked = $order->scheduled && $order->schedule_at;

        $value = match (true) {
            (bool) $booked => $order->schedule_at,
            $stage === 'processing' => $order->processing ?: $order->created_at,
            default => $order->created_at,
        };

        try {
            return $value ? Carbon::parse($value) : Carbon::now();
        } catch (\Exception $e) {
            return Carbon::now();
        }
    }
}
