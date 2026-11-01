<?php

namespace App\Services\Vendor;

use App\CentralLogics\Helpers;
use App\Traits\Order\DeliveryFeeTrait;
use App\Traits\Payment\ProCustomerSubscriptionTrait;
use App\Services\Order\DeliveryChargeService;
use App\Services\Zone\AdditionalDeliveryChargeService;
use App\Services\System\BusinessSettingService;

/**
 * Everything vendor-views/pos/_cart.blade.php used to compute inline.
 *
 * The partial renders from two paths — included by pos/index.blade.php and returned bare
 * by POSController@cart_items, which passes no data — so this is wired through a view
 * composer rather than a controller, and both paths get identical values.
 *
 * The logic is a straight lift of the original Blade blocks: same order, same operators,
 * same loose comparisons. Do not "clean up" the comparisons here; several of them
 * (`$tax_included == 1`, `isset($cart['paid'])`) depend on loose semantics.
 */
class PosCartSummary
{
    use DeliveryFeeTrait;
    use ProCustomerSubscriptionTrait;

    public function build(mixed $posCartModuleZone): array
    {
        [$rows, $subtotal, $addon_price, $discount_on_product, $cart_quantity] = $this->cartRows();

        $cart = session()->has('cart') && count(session()->get('cart')) > 0
            ? session()->get('cart')
            : null;

        $discount = 0;
        $discount_type = 'amount';
        if ($cart !== null && isset($cart['discount'])) {
            $discount = $cart['discount'];
            $discount_type = $cart['discount_type'];
        }

        $sessionAddress = session()->get('address');
        $hasRealDeliveryAddress = is_array($sessionAddress)
            && ! empty($sessionAddress['latitude'])
            && ! empty($sessionAddress['longitude']);

        $add = false;
        $delivery_fee = 0;
        if ($hasRealDeliveryAddress) {
            $add = true;
            $delivery_fee = $sessionAddress['delivery_fee'] ?? 0;
        }

        $total = $subtotal + $addon_price;

        if ($discount_type == 'percent' && $discount > 0) {
            $discount_amount = (($total - $discount_on_product) * $discount) / 100;
        } else {
            $discount_amount = $discount;
        }

        $total -= $discount_amount + $discount_on_product;

        $tax_amount = session()->get('tax_amount');
        $tax_included = session()->get('tax_included');

        if ($tax_included == 1) {
            $tax_amount = 0;
        }

        $base_delivery_fee = (float) $delivery_fee;
        $pos_order_type = (string) (session('order_type') ?? 'delivery');
        $pos_vendor_store = Helpers::get_store_data();

        $pos_eligible_amount = max(0, $subtotal + $addon_price - $discount_on_product - ($discount_amount ?? 0) - (float) (session()->get('extra_discount_amount') ?? 0));
        $pos_free_delivery = $this->effectiveFee(
            $base_delivery_fee,
            $pos_vendor_store,
            $pos_eligible_amount,
            $this->resolveCouponCodeFromSession(),
        );
        $delivery_fee = (float) $pos_free_delivery['fee'];
        $pos_is_free_delivery = (bool) $pos_free_delivery['is_free'];
        $delivery_fee_for_selector = $delivery_fee;

        $pivot = $posCartModuleZone;
        // The Additional Charge setup answers this, not `additional_delivery_option_status` --
        // that column belongs to the predecessor feature and nothing writes it any more, so the
        // POS hid the picker for every pair configured through the current screen.
        $pos_saver_enabled = $pivot
            && app(AdditionalDeliveryChargeService::class)->activeSetup($pivot->zone_id, $pivot->module_id) !== null;
        // Matches DeliveryChargeService::quote()'s own rate resolution (and
        // POSDeliveryTypeTrait::applySaverToOrder(), which the order actually placed here goes
        // through): an active DeliveryRule's minimum supersedes the pivot's own column. Reading
        // the pivot column directly let this preview show a floor the order itself did not use.
        $pos_min_charge = $pivot
            ? (float) (app(DeliveryChargeService::class)->deliveryFloor($pivot->zone_id, $pivot->module_id, $pivot) ?? 0)
            : 0.0;

        $is_delivery_context = $pos_saver_enabled && $pos_order_type === 'delivery';

        // Neither option is gated on how $delivery_fee compares to $pos_min_charge — matching
        // POSDeliveryTypeTrait::applySaverToOrder(), which applies both unconditionally "on
        // purpose" (TC_445). Express is a flat premium independent of the base fee entirely.
        // Slightly Delay's reduction is separately clamped below at max(0, fee - floor), so a fee
        // already at or below the floor (a Pro customer's delivery-fee benefit, a coupon, ...)
        // just reduces by $0 there — still a valid, selectable choice, not one to gate out here.
        // An earlier version of this gated Slightly Delay on `$delivery_fee >= $pos_min_charge`,
        // which silently made it unselectable for exactly the customers most likely to have a
        // fee under the floor.
        $is_express_eligible = $is_delivery_context;
        $is_slightly_delay_eligible = $is_delivery_context;

        $deliveryType = session()->get('delivery_type', '');
        $deliveryTypeCharge = ($is_express_eligible || $is_slightly_delay_eligible) ? (float) session()->get('delivery_type_charge', 0) : 0;
        $isExpressDelivery = $is_express_eligible && $deliveryType === 'express' && $deliveryTypeCharge > 0;
        $isSlightlyDelay = $is_slightly_delay_eligible && $deliveryType === 'slightly_delay' && $deliveryTypeCharge > 0;

        $pos_pro_discount = (float) session()->get('pos_pro_discount', 0);
        $pos_pro_benefit_type = session()->get('pos_pro_benefit_type');

        $adjustedDeliveryFee = $delivery_fee;
        if ($isExpressDelivery) {
            $adjustedDeliveryFee = $delivery_fee + $deliveryTypeCharge;
        } elseif ($isSlightlyDelay) {
            // The reduction actually applied is capped at max(0, fee - floor), matching
            // POSDeliveryTypeTrait::applySaverToOrder() exactly — re-clamped here rather than
            // trusted from session, since a stale or directly-posted $deliveryTypeCharge could
            // still carry an unclamped value. The old `max($pos_min_charge, fee - charge)` clamp
            // was wrong whenever the fee already sat below the floor (a Pro customer's
            // delivery-fee benefit, a coupon, ...): it returned the floor itself — a "discount"
            // priced higher than the undiscounted fee — instead of leaving the fee unchanged.
            $reduceApplied = min($deliveryTypeCharge, max(0, $delivery_fee - $pos_min_charge));
            $adjustedDeliveryFee = $delivery_fee - $reduceApplied;
        }

        // Applied against $adjustedDeliveryFee -- the fee as the vendor actually set it up,
        // Express premium or Slightly Delay reduction already folded in -- not against the
        // pre-saver $delivery_fee. A Slightly Delay reduction already lowers what is charged
        // before Pro ever runs, so discounting the pre-reduction base overstated the benefit.
        // Matches POSController::place_order()'s own equivalent step.
        $pos_pro_offer = [
            'status' => $pos_pro_benefit_type !== null,
            'benefit' => [
                'type' => $pos_pro_benefit_type,
                'offer_type' => session()->get('pos_pro_delivery_offer_type'),
                'charge_discount_percentage' => session()->get('pos_pro_delivery_percentage'),
                'min_order_amount' => session()->get('pos_pro_min_order_amount'),
                'min_order_status' => session()->get('pos_pro_min_order_status'),
            ],
        ];
        $proDeliveryApply = $this->applyProCustomerDeliveryFee(
            $pos_pro_offer,
            $adjustedDeliveryFee,
            $total - $pos_pro_discount,
            null,
            $pos_vendor_store?->module_type,
        );
        $pos_pro_delivery_savings = (float) ($proDeliveryApply['savings'] ?? 0);

        $total -= $pos_pro_discount;
        $total += $adjustedDeliveryFee;
        $total -= $pos_pro_delivery_savings;

        $deliveryTypeLabels = [
            'standard' => translate('messages.standard'),
            'express' => translate('messages.express'),
            'slightly_delay' => translate('messages.Slightly delay'),
        ];
        $deliveryTypeSuffix = ($isExpressDelivery || $isSlightlyDelay)
            ? ' ('.($deliveryTypeLabels[$deliveryType] ?? '').')'
            : ($pos_is_free_delivery && $pos_order_type === 'delivery' ? ' ('.translate('Free delivery').')' : '');

        $extra_discount_amount = session()->get('extra_discount_amount') ?? 0;
        $extra_discount_type = session()->get('extra_discount_type');
        $extra_discount = session()->get('extra_discount') ?? 0;

        $total -= $extra_discount_amount;

        if (isset($cart['paid'])) {
            $paid = $cart['paid'];
            $change = $total + $tax_amount - $paid;
        } else {
            $paid = $total + $tax_amount;
            $change = 0;
        }

        return [
            'cart_rows' => $rows,
            'cart_quantity' => $cart_quantity,
            'module_type' => $pos_vendor_store?->module_type,
            'subtotal' => $subtotal,
            'addon_price' => $addon_price,
            'discount_on_product' => $discount_on_product,
            'total' => $total,
            'tax_amount' => $tax_amount,
            'tax_included' => $tax_included,
            'add' => $add,
            // Resolved only when the payment-method block that reads it is rendered,
            // matching the original @if ($add) placement.
            'cod' => $add ? app(BusinessSettingService::class)->value('cash_on_delivery') : null,
            'paid' => $paid,
            'change' => $change,
            'cart_has_address' => $hasRealDeliveryAddress,
            'pos_order_type' => $pos_order_type,
            'delivery_fee_for_selector' => $delivery_fee_for_selector,
            'adjustedDeliveryFee' => $adjustedDeliveryFee,
            'deliveryTypeSuffix' => $deliveryTypeSuffix,
            'pos_pro_discount' => $pos_pro_discount,
            'pos_pro_delivery_savings' => $pos_pro_delivery_savings,
            'pos_surge_note' => $delivery_fee > 0 ? session()->get('address.surge_note') : null,
            'extra_discount_amount' => $extra_discount_amount,
            'extra_discount_type' => $extra_discount_type,
            'extra_discount' => $extra_discount,
            // session()->has() is false for a null value, so this is not the same as get().
            'old' => session()->has('address') ? session()->get('address') : null,
        ];
    }

    /**
     * Cart line items plus the running totals the table used to accumulate.
     * Non-array entries ('discount', 'discount_type', 'paid') are skipped exactly as the
     * original @if (is_array($cartItem)) did.
     *
     * `max` is the ceiling the quantity stepper clamps to: the tracked stock and the
     * item's own cart limit, whichever binds first. Food lines carry a null
     * stock_quantity, so they fall back to the cart limit alone.
     *
     * @return array{0: array<int, array{key: mixed, item: array, product_subtotal: float|int, max: int|float}>, 1: float|int, 2: float|int, 3: float|int, 4: int}
     */
    private function cartRows(): array
    {
        $rows = [];
        $subtotal = 0;
        $addon_price = 0;
        $discount_on_product = 0;
        $cart_quantity = 0;

        if (! session()->has('cart') || count(session()->get('cart')) === 0) {
            return [$rows, $subtotal, $addon_price, $discount_on_product, $cart_quantity];
        }

        foreach (session()->get('cart') as $key => $cartItem) {
            if (! is_array($cartItem)) {
                continue;
            }

            $product_subtotal = $cartItem['price'] * $cartItem['quantity'];
            $discount_on_product += $cartItem['discount'] * $cartItem['quantity'];
            $subtotal += $product_subtotal;
            $addon_price += $cartItem['addon_price'];
            $cart_quantity += (int) $cartItem['quantity'];

            $rows[] = [
                'key' => $key,
                'item' => $cartItem,
                'product_subtotal' => $product_subtotal,
                'max' => (isset($cartItem['stock_quantity']) && $cartItem['stock_quantity'] > 0)
                    ? ($cartItem['maximum_cart_quantity']
                        ? min($cartItem['stock_quantity'], $cartItem['maximum_cart_quantity'])
                        : $cartItem['stock_quantity'])
                    : ($cartItem['maximum_cart_quantity'] ?? 9999999999),
            ];
        }

        return [$rows, $subtotal, $addon_price, $discount_on_product, $cart_quantity];
    }
}
