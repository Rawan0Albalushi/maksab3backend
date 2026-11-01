<?php

namespace App\Traits\Order;

use App\Services\Marketing\CashBackService;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\EtaConfigurationService;
use App\Services\Zone\ModuleZoneDeliveryOptionService;
use App\Services\Zone\ModuleZoneService;
use App\Services\Zone\SurgePriceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * §14.4 — everything the checkout page shows, in one call.
 *
 * ## Why this lives beside placement rather than in a service
 *
 * **N4: the quote equals the charge.** Whatever this returns, place_order must charge. The only
 * way to guarantee that is to run the SAME code, so every figure below comes from a method
 * placement itself calls — `getCalculatedTax()`, `getZoneAndStore()`, `getDeliveryCharge()`,
 * `effectiveFee()`, `applyProCustomerDeliveryFee()`. Nothing here re-derives a fee, a tax or a
 * discount; it arranges what those already answer into the shape a checkout renders.
 *
 * A service that reimplemented the pipeline would pass its own tests and still drift from
 * placement on the first change to either — which is M12 restated, and the reason §12 insists on
 * one method with five callers.
 *
 * **Requires {@see PlaceNewOrderTrait} on the same class.** Both are flattened into it, so the
 * private placement helpers are reachable here.
 *
 * ## A quote is not an order
 *
 * Two deliberate relaxations against placement:
 *
 *  - **A closed store still gets a summary.** The store being shut — or switched off — must not
 *    cost the customer the delivery figure. place_order stays the one place that refuses.
 *  - **A coverage pick is validated but not required.** A quote before the customer has chosen
 *    is legitimate (§5.4); a pick belonging to another zone is still refused, on every path.
 */
trait CheckoutSummaryTrait
{
    /**
     * @return array{status_code:int,errors?:array<int,array{code:string,message:string}>,summary?:array<string,mixed>}
     */
    public function buildCheckoutSummary(Request $request): array
    {
        $moduleId = getModuleId($request->header('moduleId'));
        $scheduleAt = $request->schedule_at ? Carbon::parse($request->schedule_at) : now();

        // The same call `get-Tax` answers with, so the checkout cannot be shown one tax figure
        // and charged another. Its failures are the cart's — an expired coupon, an item that
        // went out of stock — and are reported verbatim rather than re-worded here.
        $taxResponse = $this->getCalculatedTax($request, skipStockCheck: true, skipPrescriptionCheck: true);

        if ($taxResponse->getStatusCode() !== 200) {
            return [
                'status_code' => $taxResponse->getStatusCode(),
                'errors' => data_get($taxResponse->getData(true), 'errors', []),
            ];
        }

        $tax = $taxResponse->getData(true);

        $zoneAndStore = $this->getZoneAndStore($request, $scheduleAt);

        if (data_get($zoneAndStore, 'status_code') === 403) {
            return $this->checkoutSummaryError($zoneAndStore);
        }

        $store = $zoneAndStore['store'];
        $zone = $zoneAndStore['zone'];

        $validation = $this->zoneAndStoreValidationCheck($request, $scheduleAt, $zone, $store, allowClosed: true);

        if ($validation) {
            return $this->checkoutSummaryError($validation);
        }

        $coverageError = app(DeliveryRuleService::class)->coverageSelectionError(
            zoneId: $zone?->id,
            moduleId: $moduleId,
            isSelfDelivery: (int) ($store?->sub_self_delivery ?? 0) === 1,
            orderType: (string) $request->order_type,
            areaId: $request->area_id,
            zipCodeId: $request->zip_code_id,
        );

        if ($coverageError) {
            return $this->checkoutSummaryError($coverageError);
        }

        // Resolved once and handed down, the way placement threads its own offer: every call is a
        // user lookup plus a subscription and settings read, and three independent reads of one
        // piece of state inside a single request can disagree as well as cost queries.
        $proOffer = $this->getProCustomerOffer(
            $request->user?->id,
            false,
            true,
            $store?->module?->module_type ?? 'parcel',
        );

        $delivery = $this->checkoutDeliveryBlock($request, $zone, $store, $moduleId, $tax, $proOffer);

        // Nothing is surged onto a free delivery, so the note explaining a higher fee has nothing
        // to explain — it is hidden while the charge is 0.
        //
        // The charge is the whole test. `free_delivery_by` used to be read as a second one, and
        // it does not mean what the name suggests: step 8's `partial_free` Pro benefit stamps it
        // 'admin' while leaving a fee behind, so a Pro member paying a surged 10 of 100 was told
        // nothing about the surge. Where a delivery really is free — step 7's overrides, or the
        // `full_free` benefit — the charge is already 0 and the test below catches it, which is
        // what made the extra clause redundant as well as wrong.
        $deliveryIsFree = $delivery === null
            || (float) ($delivery['delivery_charge'] ?? 0) <= 0;

        $proDiscount = (float) ($tax['pro_discount'] ?? 0);
        $proDeliverySavings = (float) data_get($delivery, 'pro_customer_savings', 0);
        $rounding = (int) config('round_up_to_digit');

        return [
            'status_code' => 200,
            'summary' => [
                'tax' => $tax,
                'delivery' => $delivery,
                'surge' => $this->checkoutSurgeBlock($zone, $moduleId, $scheduleAt, $deliveryIsFree),
                // One place to read what being Pro is worth on this basket. Both figures are
                // already inside `tax.total_price` and `delivery.delivery_charge` respectively,
                // and only one of them can ever be non-zero — the admin enables a single benefit
                // — but a checkout should not have to know which to look for, or subtract to
                // find it.
                //
                // `status` and `type` are named as `pro-customer/active-offer` names them, so the
                // two endpoints answer the same questions in the same words. What follows is this
                // basket's outcome, which is the part active-offer has no way to know: it
                // describes the entitlement, not what the entitlement was worth here.
                'pro' => [
                    'status' => (bool) ($proOffer['status'] ?? false),
                    'type' => $proOffer['benefit']['type'] ?? null,
                    'discount' => round($proDiscount, $rounding),
                    'delivery_savings' => round($proDeliverySavings, $rounding),
                    'total_savings' => round($proDiscount + $proDeliverySavings, $rounding),
                ],
                'cashback' => app(CashBackService::class)->calculateForAmount(
                    amount: $request->order_amount,
                    customerId: $request->user?->id ?? $request->guest_id ?? 'all',
                    moduleId: $moduleId,
                ),
                // The same Standard/Express/Slightly Delay rows the POS picker lists, each with
                // its own delivery-time window, so a checkout page can offer the choice before
                // placing rather than the customer only finding out what Express costs and saves
                // after the order already carries `delivery_type` (§14.4 extended to cover N4:
                // whatever this previews, place_order must be able to charge and quote the same).
                'delivery_options' => $this->checkoutDeliveryOptionsBlock(
                    $zone, $store, $moduleId, (string) $request->order_type,
                ),
            ],
        ];
    }

    /**
     * Null wherever the feature does not apply — no zone, self-delivery, not a delivery order, or
     * the admin never turned Additional Charge on for this (zone, module) — so a client renders
     * nothing rather than an empty "Delivery Type" section.
     *
     * @return array<int,array<string,mixed>>|null
     */
    private function checkoutDeliveryOptionsBlock($zone, $store, int $moduleId, string $orderType): ?array
    {
        if (! $zone || $orderType !== 'delivery') {
            return null;
        }

        if ((int) ($store?->sub_self_delivery ?? 0) === 1) {
            return null;
        }

        // Whether the option is configured is answered by the Additional Charge screen's own
        // setup, not by `module_zone.additional_delivery_option_status`. That column belongs to
        // the predecessor feature and nothing writes it any more -- no controller, no service,
        // no form input -- so a (zone, module) pair whose column was not already 1 could never
        // switch the option on however the screen was filled in.
        //
        // optionsFor() returns an empty collection when there is no active setup, so the
        // emptiness test below is the whole gate.
        $rows = app(ModuleZoneDeliveryOptionService::class)->optionsFor($moduleId, $zone->id);

        if ($rows->isEmpty()) {
            return null;
        }

        $floorMin = app(EtaConfigurationService::class)->minimumDeliveryTimeFloor($zone->id, $moduleId);

        return $this->buildDeliveryOptions($rows, $store?->delivery_time, $floorMin);
    }

    /**
     * The delivery charge as place_order would compute it, plus the pieces a checkout shows
     * separately (§12.1).
     *
     * Steps 1-6 come from `getDeliveryCharge()` — the one engine — and steps 7-9 are run in the
     * order `DeliveryFeeTrait` owns, matching placement line for line. Null when there is no zone
     * to price against.
     *
     * @return array<string,mixed>|null
     */
    private function checkoutDeliveryBlock(Request $request, $zone, $store, $moduleId, array $tax, array $proOffer): ?array
    {
        if (! $zone) {
            return null;
        }

        // A free-delivery coupon pre-sets the charge, exactly as in placement. A coupon that does
        // not validate has already been reported by the tax step above, so it is treated as no
        // coupon here rather than refused a second time in different words.
        $couponData = $this->getCouponData($request);
        $couponData = data_get($couponData, 'status_code') === 403 ? [] : $couponData;

        $coupon = data_get($couponData, 'coupon');
        $freeDeliveryBy = data_get($couponData, 'free_delivery_by');
        $presetCharge = data_get($couponData, 'delivery_charge');

        $pivotRow = $zone->modules()->where('modules.id', $moduleId)->first();

        $fee = $this->getDeliveryCharge($request, $zone, $store, $pivotRow, $presetCharge, $moduleId);

        $charge = (float) (data_get($fee, 'delivery_charge') ?? 0);
        $base = (float) data_get($fee, 'base_delivery_charge', 0);
        $surgeAmount = (float) data_get($fee, 'surge_amount', 0);

        // Step 7 — free-delivery overrides. Measured against the same amount placement measures
        // against: the basket after the store, flash-sale and coupon discounts, but BEFORE the
        // first-order bonus and the Pro discount. `tax.total_price` is that figure with both
        // already taken off, so both are added back — which is exactly how placement builds its
        // own `$eligibleAmount`, without needing a key of its own on the tax payload.
        $eligibleAmount = max(0.0, (float) ($tax['total_price'] ?? 0)
            + (float) ($tax['ref_bonus_amount'] ?? 0)
            + (float) ($tax['pro_discount'] ?? 0));

        $couponCodeForFree = ($coupon && $coupon->coupon_type === 'free_delivery') ? $coupon->code : null;
        $effective = $this->effectiveFee($charge, $store, $eligibleAmount, $couponCodeForFree);

        if ($effective['is_free']) {
            $charge = 0.0;
            $freeDeliveryBy = $effective['free_by'] === self::FREE_BY_COUPON && $coupon
                ? $coupon->created_by
                : $effective['free_by'];
        }

        // Step 8 — the post-engine percentage discount, only where nothing has already made the
        // delivery free. Measured against the post-discount total, as placement measures it.
        $proSavings = 0.0;

        if (! $freeDeliveryBy) {
            // Percentage taken against the fee as the customer actually sees it — Express
            // premium or Slightly Delay reduction already folded in — not the pre-saver $charge,
            // matching placeNewOrder()'s own equivalent step. $charge itself stays base+surge
            // only (delivery_type_charge is applied on top of it elsewhere), so the discount is
            // still subtracted from $charge directly, not from this net figure.
            $netCharge = $charge + match (data_get($fee, 'delivery_type')) {
                \App\Models\ModuleZoneDeliveryOption::TYPE_EXPRESS => (float) data_get($fee, 'delivery_type_charge', 0),
                \App\Models\ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY => -(float) data_get($fee, 'delivery_type_charge', 0),
                default => 0.0,
            };
            $proDelivery = $this->applyProCustomerDeliveryFee(
                $proOffer,
                $netCharge,
                (float) ($tax['total_price'] ?? 0),
                $freeDeliveryBy,
                $store?->module?->module_type ?? 'parcel',
            );

            $freeDeliveryBy = $proDelivery['free_delivery_by'];
            $proSavings = (float) $proDelivery['savings'];
            $charge = max(0.0, $charge - $proSavings);
        }

        $rounding = (int) config('round_up_to_digit');

        return [
            // The net figure — what the customer pays, and what the total is built from.
            'delivery_charge' => round($charge, $rounding),
            // The same charge as it stood immediately before the Pro benefit reduced it, so a
            // checkout can show the fee and the discount as two lines — "Delivery 140" then
            // "Pro discount -70" — instead of one already-netted 70 that reads as a cheap
            // delivery rather than a benefit the membership earned.
            //
            // Equal to `delivery_charge` whenever no Pro discount applied, so a client renders
            // this row unconditionally and shows the discount line only while savings are above
            // zero. Zone and vendor free delivery are deliberately NOT unwound here: they are the
            // store's price, not something taken off the customer's.
            'delivery_charge_before_pro_discount' => round($charge + $proSavings, $rounding),
            'original_delivery_charge' => round((float) data_get($fee, 'original_delivery_charge', 0), $rounding),
            'base_delivery_charge' => round($base, $rounding),
            // The lowest this order's delivery may be priced at — Delivery Rule Setup's "Minimum
            // Delivery Charge" for a (zone, module) that has an active rule, and the Module
            // Setup > Delivery Charge Setup figure underneath it for a zone that has none.
            //
            // Taken from the fee engine's own `floor`, which is the minimum of whichever branch of
            // step 2 actually priced THIS order (DeliveryChargeService::quote()). Deliberately not
            // deliveryFloor(): that answers a different question -- the SAVER floor a slightly-delay
            // reduction may not cut through -- and reads the pivot's `minimum_delivery_charge`
            // rather than the `minimum_shipping_charge` the fee is really floored at, so it would
            // report a number this order is not held to.
            'min_delivery_charge' => round((float) data_get($fee, 'floor', 0), $rounding),
            'surge_amount' => round($surgeAmount, 2),
            'free_delivery_by' => $freeDeliveryBy,
            'pro_customer_savings' => round($proSavings, 2),
            'vehicle_id' => data_get($fee, 'vehicle_id'),
        ];
    }

    /**
     * The surge in force for the zone that prices this order, or null.
     *
     * The STORE's zone, not the `zoneId` header: the header can carry several ids where zones
     * overlap, and the fee above was computed for exactly one of them. Reading a different one
     * here would explain a surge the customer was not charged, or stay silent about one they were
     * (§9.2).
     *
     * @return array<string,mixed>|null
     */
    private function checkoutSurgeBlock($zone, $moduleId, $scheduleAt, bool $hideCustomerNote): ?array
    {
        if (! $zone) {
            return null;
        }

        $surge = $this->getSurgePriceValue($zone->id, $moduleId, $scheduleAt);

        if ((float) ($surge['price'] ?? 0) <= 0) {
            return null;
        }

        $surge['customer_note'] = $hideCustomerNote
            ? ''
            : (app(SurgePriceService::class)->customerNote($surge) ?? '');

        if ($hideCustomerNote) {
            $surge['customer_note_status'] = 0;
        }

        $surge['zone_id'] = (int) $zone->id;

        return $surge;
    }

    /** @return array{status_code:int,errors:array<int,array{code:string,message:string}>} */
    private function checkoutSummaryError(array $failure): array
    {
        return [
            'status_code' => (int) data_get($failure, 'status_code', 403),
            'errors' => [[
                'code' => (string) data_get($failure, 'code', 'order'),
                'message' => data_get($failure, 'message'),
            ]],
        ];
    }
}
