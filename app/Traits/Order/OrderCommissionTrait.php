<?php

namespace App\Traits\Order;

trait OrderCommissionTrait
{
    use DeliveryFeeTrait;

    public function originalAdminCommissionDetails($orderTransaction): array
    {
        $order = $orderTransaction?->order;

        if (! $order) {
            return [
                'original_admin_commission' => 0,
                'item_price_after_admin_commission' => 0,
            ];
        }
        $totalItemAmount = (float) ($order->order_amount ?? 0)
            - (float) ($orderTransaction->additional_charge ?? 0)
            - (float) ($order->dm_tips ?? 0)
            - (float) ($order->delivery_charge ?? 0)
            - (float) ($orderTransaction->tax ?? 0)
            - (float) ($order->extra_packaging_amount ?? 0)
            + (float) ($order->coupon_discount_amount ?? 0)
            + (float) ($order->store_discount_amount ?? 0)
            + (float) ($order->ref_bonus_amount ?? 0)
            + (float) ($order->flash_admin_discount_amount ?? 0)
            + (float) ($order->flash_store_discount_amount ?? 0)
            + (float) ($order->extra_discount_amount ?? 0);

        $originalAdminCommission = (float) ($orderTransaction->admin_commission ?? 0)
            + (float) ($orderTransaction->admin_expense ?? 0)
            - (float) ($orderTransaction->delivery_fee_comission ?? 0)
            - (float) ($orderTransaction->additional_charge ?? 0)
            - (float) ($order->flash_admin_discount_amount ?? 0);

        $itemPriceAfterAdminCommission = $totalItemAmount - $originalAdminCommission;

        return [
            'original_admin_commission' => $originalAdminCommission,
            'item_price_after_admin_commission' => $itemPriceAfterAdminCommission,
        ];
    }

    public function adminItemCommission($orderTransaction): float
    {
        $order = $orderTransaction?->order;
        if (! $order) {
            return 0;
        }

        if ($orderTransaction->is_subscribed) {
            return 0;
        }

        $commission_percentage = (float) ($orderTransaction->commission_percentage ?? 0);
        if ($commission_percentage <= 0) {
            return (float) ($orderTransaction->admin_commission ?? 0)
                + (float) ($orderTransaction->admin_expense ?? 0)
                - (float) ($orderTransaction->delivery_fee_comission ?? 0)
                - (float) ($orderTransaction->additional_charge ?? 0)
                - (float) ($order->flash_admin_discount_amount ?? 0);
        }

        $item_amount = (float) ($order->order_amount ?? 0)
            - (float) ($orderTransaction->additional_charge ?? 0)
            - (float) ($order->dm_tips ?? 0)
            - (float) $this->adjustedFeeForOrder($order)['adjusted']
            - (float) ($orderTransaction->tax ?? 0)
            + (float) ($order->coupon_discount_amount ?? 0)
            + (float) ($order->store_discount_amount ?? 0)
            + (float) ($order->flash_admin_discount_amount ?? 0)
            + (float) ($order->flash_store_discount_amount ?? 0)
            + (float) ($order->ref_bonus_amount ?? 0)
            - (float) ($order->extra_packaging_amount ?? 0)
            + (float) ($order->extra_discount_amount ?? 0)
            + (float) ($orderTransaction->pro_discount ?? 0);

        return $item_amount * $commission_percentage / 100;
    }

    public function proDiscountTotal($order): float
    {
        $pro = $order->orderProDiscount ?? null;

        if (! $pro) {
            return 0.0;
        }

        return (float) ($pro->amount_saved ?? 0)
            + (float) ($pro->delivery_fee_reduction_amount ?? 0);
    }

    /**
     * SQL twin of adminNetIncome(), for aggregating over many rows at once. Reports need the
     * total across a date range, and looping the PHP version costs minutes at production
     * volume -- 2M transactions is ~4,000 chunk queries and 2M hydrations for one scalar.
     *
     * The two must stay in lockstep: nothing checks it, so change one and change the other.
     *
     * $t and $o are the table aliases to read from, defaulting to `t` and `o`. A caller that
     * cannot alias order_transactions -- because its own predicates already name the table --
     * passes the table name instead.
     */
    public function adminNetIncomeSql(string $t = 't', string $o = 'o'): string
    {
        $sql = <<<'SQL'
            CASE WHEN o.order_type = 'parcel' THEN
                   t.admin_commission + t.delivery_fee_comission + t.additional_charge - COALESCE(t.admin_expense, 0)
                 ELSE
                   (CASE
                      WHEN t.is_subscribed = 1 THEN 0
                      WHEN COALESCE(t.commission_percentage, 0) <= 0 THEN
                        t.admin_commission + COALESCE(t.admin_expense, 0) - t.delivery_fee_comission
                        - t.additional_charge - o.flash_admin_discount_amount
                      ELSE
                        ( o.order_amount
                          - t.additional_charge
                          - o.dm_tips
                          - (CASE
                               WHEN o.delivery_type = 'express' AND o.delivery_type_charge > 0 AND o.delivery_charge > 0
                                 THEN o.delivery_charge + o.delivery_type_charge
                               WHEN o.delivery_type = 'slightly_delay' AND o.delivery_type_charge > 0 AND o.delivery_charge > 0
                                 THEN GREATEST(0, o.delivery_charge - o.delivery_type_charge)
                               ELSE o.delivery_charge
                             END)
                          - t.tax
                          + o.coupon_discount_amount
                          + o.store_discount_amount
                          + o.flash_admin_discount_amount
                          + o.flash_store_discount_amount
                          + o.ref_bonus_amount
                          - o.extra_packaging_amount
                          + o.extra_discount_amount
                          + t.pro_discount
                        ) * COALESCE(t.commission_percentage, 0) / 100
                    END)
                   + t.delivery_fee_comission
                   + t.additional_charge
                   - COALESCE(t.admin_expense, 0)
                   + (CASE WHEN o.delivery_type = 'express' THEN o.delivery_type_charge ELSE 0 END)
            END
            SQL;

        return $t === 't' && $o === 'o'
            ? $sql
            : str_replace(['t.', 'o.'], [$t.'.', $o.'.'], $sql);
    }

    public function adminNetIncome($orderTransaction): float
    {
        $order = $orderTransaction->order;

        if ($order?->order_type === 'parcel') {
            return (float) ($orderTransaction->admin_commission ?? 0)
                + (float) ($orderTransaction->delivery_fee_comission ?? 0)
                + (float) ($orderTransaction->additional_charge ?? 0)
                - (float) ($orderTransaction->admin_expense ?? 0);
        }

        return $this->adminItemCommission($orderTransaction)
            + (float) ($orderTransaction->delivery_fee_comission ?? 0)
            + (float) ($orderTransaction->additional_charge ?? 0)
            - (float) ($orderTransaction->admin_expense ?? 0)
            + ($order?->delivery_type === 'express' ? (float) $order->delivery_type_charge : 0);
    }
}
