<?php

namespace App\Traits\Order;

use App\Models\ModuleZoneDeliveryOption;
use App\Services\Zone\FreeDeliveryService;
use App\Services\Marketing\CouponService;

/**
 * Steps 7 to 9 of the fee pipeline (port doc §12), in one place so no caller inlines the sequence.
 *
 * ## §8.4 — compounding, decided
 *
 * A `slightly_delay` reduction and a post-engine percentage discount both land on the same fee.
 * The setup-time guard on the reduction (§8.1) is config-time and cannot see the discount, so on
 * their own the two could cross the floor together.
 *
 * **Decision: (a) — the reduction is ALSO clamped at runtime.**
 * `PlaceNewOrderTrait::resolveSaverDeliveryType()` limits it to `fee - floor`, where the floor is
 * `DeliveryChargeService::deliveryFloor()` — the active delivery rule's minimum, or the pivot's
 * where no rule exists. The clamp is what stops the pair crossing the floor; the setup guard just
 * stops an admin configuring something that would always have been clamped.
 *
 * Option (b), accepting the compounded case, was not taken: a delivery priced under its own
 * configured floor is a fee nobody agreed to, and this is exactly the shape M7 took.
 */
trait DeliveryFeeTrait
{
    public const FREE_BY_ADMIN = 'admin';

    public const FREE_BY_VENDOR = 'vendor';

    public const FREE_BY_COUPON = 'coupon';

    public function applyDeliveryTypeToAmount($order, float $amount): float
    {
        $rounding = (int) config('round_up_to_digit');

        // A zeroed delivery_charge means two different things, and only one of them should erase
        // the delivery type: no delivery pricing applies at all (no rule, self-delivery, pickup),
        // versus free delivery zeroing what would otherwise have been a real charge. free_delivery_by
        // is set only in the second case, and an express/slightly-delay choice made against a base
        // fee that free delivery then waived is still a choice the customer made and, for express,
        // still paid an extra premium for — losing that designation silently understated what was
        // actually delivered (TC_445). Every caller of this method already sets free_delivery_by
        // on the order before calling it, so this reads true state, not a guess.
        if ((float) ($order->delivery_charge ?? 0) <= 0 && ! $order->free_delivery_by) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;

            return $amount;
        }

        $isSaverType = in_array($order->delivery_type, [ModuleZoneDeliveryOption::TYPE_EXPRESS, ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY], true);
        if ($isSaverType && (int) ($order->store?->sub_self_delivery ?? 0) === 1) {
            $order->delivery_type = ModuleZoneDeliveryOption::TYPE_STANDARD;
            $order->delivery_type_charge = 0;

            return $amount;
        }

        if ($order->delivery_type === ModuleZoneDeliveryOption::TYPE_EXPRESS) {
            return round($amount + (float) $order->delivery_type_charge, $rounding);
        }

        if ($order->delivery_type === ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY) {
            return round($amount - (float) $order->delivery_type_charge, $rounding);
        }

        return $amount;
    }

    public function effectiveFee(
        float $baseFee,
        $store,
        float $eligibleOrderAmount,
        ?string $couponCode = null
    ): array {
        if ($baseFee <= 0) {
            return ['fee' => 0.0, 'free_by' => null, 'is_free' => false];
        }

        // Step 7a — the zone/module free delivery setup (§10).
        //
        // This REPLACES the three global business settings this used to read
        // (`admin_free_delivery_status`, `admin_free_delivery_option`, `free_delivery_over`).
        // They are deprecated by owner decision: only the per-(zone, module) setups count, with
        // no fallback. An install that had the global switched on therefore stops giving free
        // delivery until an admin creates setups — deliberate, and recorded in the S6 report.
        //
        // Zone and module come from the STORE rather than the request's zone header. The store is
        // where the order is served from, so its zone is unambiguous, while the header can carry
        // several ids for overlapping zones. `activeSetup()` still takes a list and honours
        // §10.3's "first zone with a setup wins" for any caller that has one.
        if ($store?->zone_id && $store?->module_id) {
            $frees = app(FreeDeliveryService::class)->frees(
                [$store->zone_id],
                $store->module_id,
                // §10.3 — measured against the POST-discount total, which is what the callers
                // already pass as the eligible amount.
                $eligibleOrderAmount,
            );

            if ($frees) {
                return ['fee' => 0.0, 'free_by' => self::FREE_BY_ADMIN, 'is_free' => true];
            }
        }

        if ($store && (int) ($store->free_delivery ?? 0) === 1) {
            return ['fee' => 0.0, 'free_by' => self::FREE_BY_VENDOR, 'is_free' => true];
        }

        if ($couponCode !== null && $couponCode !== '') {
            $coupon = app(CouponService::class)->findActiveFreeDelivery($couponCode);
            if ($coupon && (float) ($coupon->min_purchase ?? 0) <= $eligibleOrderAmount) {
                return ['fee' => 0.0, 'free_by' => self::FREE_BY_COUPON, 'is_free' => true];
            }
        }

        return ['fee' => $baseFee, 'free_by' => null, 'is_free' => false];
    }

    public function adjustedFeeForOrder($order): array
    {
        $base = (float) ($order->delivery_charge ?? 0);
        $type = (string) ($order->delivery_type ?? '');
        $typeCharge = (float) ($order->delivery_type_charge ?? 0);
        $freeBy = $order->free_delivery_by ?? null;

        // No `$base > 0` requirement: since applyDeliveryTypeToAmount() was fixed to keep express
        // honoured (and its premium charged) even when free delivery zeroes the base, an order can
        // legitimately be delivery_type=express with base=0 and typeCharge>0. Requiring a nonzero
        // base here made that combination invisible on every screen this row renders on (order
        // view, invoice, POS invoice) — the premium was still correctly charged and recorded, it
        // just never showed. Slightly-delay's own charge is already 0 whenever the base was free
        // (resolveSaverDeliveryType() floors the reduction at the base itself), so dropping the
        // base check here does not change when that case renders.
        $isExpress = $type === 'express' && $typeCharge > 0;
        $isSlightly = $type === 'slightly_delay' && $typeCharge > 0;
        $isFree = ! $isExpress && ! $isSlightly && $freeBy && $base <= 0;

        $adjustedFee = $base;
        if ($isExpress) {
            $adjustedFee = $base + $typeCharge;
        } elseif ($isSlightly) {
            $adjustedFee = max(0, $base - $typeCharge);
        }

        $suffix = '';
        if ($isExpress) {
            $suffix = ' ('.translate('messages.express').')';
        } elseif ($isSlightly) {
            $suffix = ' ('.translate('Slightly delay').')';
        } elseif ($isFree) {
            $suffix = ' ('.translate('Free delivery').')';
        }

        return [
            'base' => $base,
            'adjusted' => $adjustedFee,
            'type_charge' => $typeCharge,
            'is_express' => $isExpress,
            'is_slightly' => $isSlightly,
            'is_free' => $isFree,
            'free_by' => $freeBy,
            'suffix' => $suffix,
        ];
    }

    public function proDeliveryBreakdown($order): array
    {
        $pro = $order->orderProDiscount ?? null;

        $reduction = 0.0;
        if ($pro && ($pro->benefit_type ?? null) === 'delivery_fee') {
            $reduction = (float) ($pro->delivery_fee_reduction_amount ?? 0);
        }

        $charged = (float) ($order->delivery_charge ?? 0);

        return [
            'has_reduction' => $reduction > 0,
            'reduction' => $reduction,
            'charged_fee' => $charged,
            'original_fee' => $charged + $reduction,
        ];
    }

    public function resolveCouponCodeFromSession(): ?string
    {
        foreach (['coupon_code', 'coupon'] as $key) {
            $value = session()->get($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (is_array($value) && ! empty($value['code'])) {
                return (string) $value['code'];
            }
        }

        return null;
    }
}
