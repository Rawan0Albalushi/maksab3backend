<?php

namespace App\Traits\Marketing;

trait CouponDiscountTrait
{
    public function calculateDiscount(mixed $coupon, mixed $orderAmount): mixed
    {
        $discountAmount = match (true) {
            $coupon->discount_type == 'percent' && $coupon->discount > 0 => $orderAmount * ($coupon->discount / 100),
            default => $coupon->discount,
        };

        return $coupon->max_discount > 0 && $discountAmount > $coupon->max_discount
            ? $coupon->max_discount
            : $discountAmount;
    }
}
