<?php

namespace App\Traits\Order;

use App\Models\ModuleZoneDeliveryOption;
use Illuminate\Http\Request;
use App\Services\Zone\ModuleZoneService;
use App\Services\Order\DeliveryChargeService;
use App\Services\Zone\ModuleZoneDeliveryOptionService;
use App\Services\Zone\EtaConfigurationService;
use App\Services\Order\EtaService;

trait POSDeliveryTypeTrait
{
    public function loadDeliveryTypes(int $moduleId, int $zoneId, ?float $currentDeliveryCharge = null, ?bool $storeSelfDelivery = null, ?string $storeDeliveryTime = null): array
    {
        $empty = [
            'enabled'                 => false,
            'options'                 => [],
            'minimum_delivery_time'   => 0,
            'minimum_delivery_charge' => 0.0,
            'current_delivery_charge' => $currentDeliveryCharge !== null ? (float) $currentDeliveryCharge : 0.0,
            'reason'                  => 'not_configured',
        ];

        if ($moduleId <= 0 || $zoneId <= 0) {
            return $empty;
        }

        if ($storeSelfDelivery === true) {
            return array_merge($empty, ['reason' => 'self_delivery_on']);
        }

        // The pivot is still read for the charge floor below, but no longer asked whether the
        // option is ON: that column belongs to the predecessor feature and nothing writes it any
        // more, so it reported OFF for every pair configured through the Additional Charge
        // screen. `no_options` below answers from the setup itself.
        $pivot = app(ModuleZoneService::class)->findForModuleAndZone($moduleId, $zoneId);

        if (!$pivot) {
            return $empty;
        }

        // §8.2 — the charge floor comes from the (zone, module)'s active delivery rule where
        // there is one, and from the pivot otherwise. The time floor comes from the (zone,
        // module)'s active ETA Configuration — `module_zone.minimum_delivery_time` has no admin
        // screen left to write it. One resolver each, so POS, placement and the setup screen
        // cannot disagree about where a floor is.
        $minDeliveryCharge = (float) (app(DeliveryChargeService::class)->deliveryFloor($zoneId, $moduleId, $pivot) ?? 0);
        $minDeliveryTime = app(EtaConfigurationService::class)->minimumDeliveryTimeFloor($zoneId, $moduleId);
        $orderType = (string) (session('order_type') ?? '');
        $currentFee = $currentDeliveryCharge !== null
            ? (float) $currentDeliveryCharge
            : (float) (session('address.delivery_fee') ?? 0);

        if ($orderType !== '' && $orderType !== 'delivery') {
            return array_merge($empty, [
                'reason' => 'not_delivery_order',
            ]);
        }

        // Neither option needs excluding just because the current fee sits below the zone's
        // minimum charge (most commonly a Pro customer's delivery-fee discount landing under the
        // floor). Express is a flat premium independent of the base fee entirely (TC_445).
        // Slightly Delay's own reduction is separately clamped by applySaverToOrder() at
        // max(0, base - floor) — a fee already at or below the floor just reduces by $0 there,
        // still a valid (if inert) choice, not one this endpoint needs to hide. An earlier version
        // of this method hid the *entire* picker in this case, then a later one hid only Slightly
        // Delay — both were wrong for the same reason: comparing the fee to the floor has no
        // bearing on which options are safe to *offer*, only on what each one's clamped price
        // preview should show (chargeText() in delivery-type-selector.js).
        //
        // Already ordered standard, express, slightly delay — the service builds them in the
        // order the POS lists them, so the FIELD() sort the query used to need is gone with it.
        $rows = app(ModuleZoneDeliveryOptionService::class)->optionsFor($moduleId, $zoneId);

        if ($rows->isEmpty()) {
            return array_merge($empty, ['reason' => 'no_options']);
        }

        $freeDeliveryActive = $currentFee <= 0;

        return [
            'enabled'                 => true,
            'options'                 => $this->buildDeliveryOptions($rows, $storeDeliveryTime, $minDeliveryTime),
            'minimum_delivery_time'   => $minDeliveryTime,
            'minimum_delivery_charge' => $minDeliveryCharge,
            'current_delivery_charge' => $currentFee,
            'free_delivery_active'    => $freeDeliveryActive,
            'reason'                  => $freeDeliveryActive ? 'free_delivery_active' : 'ok',
        ];
    }

    /**
     * Standard/Express/Slightly Delay as the POS picker and the customer app's checkout-summary
     * both list them, each carrying its own delivery-time window. Pulled out of loadDeliveryTypes()
     * so both callers build the exact same three rows from the exact same rows/floor — a checkout
     * preview and the POS must never show two different windows for the same (zone, module, store).
     *
     * @param \Illuminate\Support\Collection<int,ModuleZoneDeliveryOption> $rows
     * @return array<int,array<string,mixed>>
     */
    protected function buildDeliveryOptions($rows, ?string $storeDeliveryTime, int $floorMin): array
    {
        // The same store-time-vs-floor window shown on the picker itself (delivery-type-selector.js
        // rangeText()), computed here too so a caller reading this JSON directly — a receipt, an
        // order-confirmation screen, anything that isn't the POS's own JS — sees the identical text
        // rather than having to re-derive it (and risk drifting from the picker's own logic).
        [$storeMin, $storeMax] = app(EtaService::class)->parseDeliveryTime($storeDeliveryTime);

        return $rows->map(function (ModuleZoneDeliveryOption $row) use ($storeMin, $storeMax, $floorMin) {
            $addTime = (int) ($row->getRawOriginal('add_delivery_time') ?? 0);
            $reduceTime = (int) ($row->getRawOriginal('reduce_delivery_time') ?? 0);

            return [
                'id'                   => (int) $row->id,
                'delivery_type'        => (string) $row->delivery_type,
                'delivery_type_text'   => translate($row->delivery_type),
                'extra_charge'         => (float) ($row->extra_charge ?? 0),
                'reduce_charge'        => (float) ($row->reduce_charge ?? 0),
                'add_delivery_time'    => $addTime,
                'reduce_delivery_time' => $reduceTime,
                'time_range'           => $this->deliveryTimeRangeText(
                    $storeMin, $storeMax, $floorMin, (string) $row->delivery_type, $reduceTime, $addTime,
                ),
            ];
        })->values()->all();
    }

    /**
     * "12 min - 1 hour" / "upto 50 min" for one delivery type — the floor applied cleanly to
     * both ends (`max(store, floor)`, exactly like EtaService and saverDeliveryWindow()), then
     * only the max shifted for Express/Slightly Delay, since every option shares the same
     * earliest possible time and only the latest one changes. Mirrors delivery-type-selector.js's
     * rangeText() so the JS and this JSON field can never disagree.
     */
    private function deliveryTimeRangeText(?int $storeMin, ?int $storeMax, int $floorMin, string $type, int $reduceMin, int $addMin): ?string
    {
        if ($storeMin === null || $storeMax === null) {
            return null;
        }

        $min = max($storeMin, $floorMin);
        $max = max($storeMax, $floorMin);

        if ($type === ModuleZoneDeliveryOption::TYPE_EXPRESS) {
            // Express may not promise away more than the floored range already spans.
            $max = max($max - $reduceMin, $min);
        } elseif ($type === ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY) {
            $max += $addMin;
        }

        return $min === $max
            ? translate('messages.upto').' '.$this->formatMinutesLabel($max)
            : $this->formatMinutesLabel($min).' - '.$this->formatMinutesLabel($max);
    }

    /** "12 min" / "1 hour" / "1 hour 10 min" — matches delivery-type-selector.js's fmtMinutes(). */
    private function formatMinutesLabel(int $minutes): string
    {
        $total = max(0, $minutes);

        if ($total < 60) {
            return $total.' '.translate('ETA minute unit');
        }

        $hours = intdiv($total, 60);
        $mins = $total % 60;
        $hourLabel = $hours.' '.($hours === 1 ? translate('messages.hour') : translate('messages.hours'));

        return $mins === 0 ? $hourLabel : $hourLabel.' '.$mins.' '.translate('ETA minute unit');
    }

    public function storeDeliveryType(Request $request): void
    {
        $type = $request->input('delivery_type');
        $charge = (float) $request->input('delivery_type_charge', 0);
        $baseFee = (float) (session('address.delivery_fee') ?? 0);

        $allowed = [
            ModuleZoneDeliveryOption::TYPE_STANDARD,
            ModuleZoneDeliveryOption::TYPE_EXPRESS,
            ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY,
        ];

        if ($type === '' || $type === null || !\in_array($type, $allowed, true)) {
            session()->forget(['delivery_type', 'delivery_type_charge', 'cart_delivery_fee']);
            return;
        }

        // Previously bailed out here whenever $baseFee <= 0 (a Pro customer's "full free"
        // delivery-fee benefit, a free-delivery coupon, ...), silently discarding whatever the
        // admin had just picked. That directly contradicted applySaverToOrder() and
        // resolveSaverDeliveryType() — both explicitly skip this exact gate "on purpose" (TC_445),
        // since Express is a flat premium independent of the base fee. This was the one place left
        // in the chain that still disagreed: the picker let the admin choose Express, and this
        // endpoint then wiped the choice back out before it ever reached order placement.

        session()->put('delivery_type', $type);
        session()->put('delivery_type_charge', \max(0.0, $charge));

        $finalCharge = $baseFee;
        if ($charge > 0) {
            if ($type === ModuleZoneDeliveryOption::TYPE_EXPRESS) {
                $finalCharge = $baseFee + $charge;
            } elseif ($type === ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY) {
                $finalCharge = $baseFee - $charge;
            }
        }
        session()->put('cart_delivery_fee', \max(0.0, $finalCharge));
    }


    public function clearDeliveryTypeSession(): void
    {
        session()->forget(['delivery_type', 'delivery_type_charge', 'cart_delivery_fee']);
    }

    public function applySaverToOrder($order, int $moduleId, int $zoneId, float $baseDeliveryCharge, ?bool $storeSelfDelivery = null): void
    {
        if (!$order) {
            return;
        }

        if ($storeSelfDelivery === true) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;
            return;
        }

        if ($moduleId <= 0 || $zoneId <= 0) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;
            return;
        }

        // No pivot lookup here at all now: it was read only to ask the predecessor's ON column,
        // which nothing writes any more. findForModuleZoneType() below returns null when the
        // (zone, module) has no active setup, and that already falls back to standard.

        // No `$baseDeliveryCharge <= 0` or minimum-charge eligibility gate here on purpose,
        // matching PlaceNewOrderTrait::resolveSaverDeliveryType(): the base fee being zero —
        // because free delivery applied, or because the rule's own minimum is zero — says
        // nothing about whether express or slightly-delay should be offered. Express is a flat
        // premium that does not depend on the base at all (TC_445); slightly-delay's reduction
        // below is already capped at max(0, $baseDeliveryCharge - $floor), so a zero base yields
        // a $0 reduction on its own without needing a separate guard here.
        $type = (string) (session('delivery_type') ?? '');
        if (!\in_array($type, [ModuleZoneDeliveryOption::TYPE_EXPRESS, ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY], true)) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;
            return;
        }

        $option = app(ModuleZoneDeliveryOptionService::class)->findForModuleZoneType($moduleId, $zoneId, $type);
        if (!$option) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;
            return;
        }

        $rounding = (int) (config('round_up_to_digit') ?? 2);

        if ($type === ModuleZoneDeliveryOption::TYPE_EXPRESS) {
            $finalCharge = (float) max(0, $option->extra_charge ?? 0);
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_EXPRESS;
            $order->delivery_type_charge = round($finalCharge, $rounding);
            $order->order_amount = round(((float) ($order->order_amount ?? 0)) + $finalCharge, $rounding);
            return;
        }

        // Prefers an active DeliveryRule's own minimum over the pivot column, matching
        // DeliveryChargeService::quote()'s own rate resolution — a zone/module with a delivery
        // rule is priced by that rule, so the saver's floor has to agree with it rather than
        // clamping against a pivot minimum the rule has already superseded.
        $floor = (float) (app(DeliveryChargeService::class)->deliveryFloor($zoneId, $moduleId, $pivot) ?? 0);
        $reduce = (float) max(0, $option->reduce_charge ?? 0);
        $maxReducible = max(0, $baseDeliveryCharge - $floor);
        $finalCharge = min($reduce, $maxReducible);

        $order->delivery_type = ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY;
        $order->delivery_type_charge = round($finalCharge, $rounding);
        $order->order_amount = round(((float) ($order->order_amount ?? 0)) - $finalCharge, $rounding);
    }
}
