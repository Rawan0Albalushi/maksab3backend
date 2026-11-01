<?php

namespace App\Support\Notification\Messages;

use App\Services\System\CurrencyService;

trait DeliveryManMessages
{
    public static function deliveryManReferralBonus(mixed $amount, mixed $historyId): array
    {
        return [
            'title' => translate('Referral bonus'),
            'description' => translate('Congratulations! You have received a referral bonus').': '.app(CurrencyService::class)->format($amount),
            'data_id' => $historyId,
            'image' => '',
            'type' => 'deliveryman_referral',
        ];
    }
    public static function deliveryManAccountActivated(): array
    {
        return self::make(
            translate('messages.Account activation'),
            translate('messages.Your account has been activated'),
            ['type' => 'unblock'],
        );
    }
    public static function deliveryManAccountSuspended(): array
    {
        return self::make(
            translate('messages.suspended'),
            translate('messages.Your account has been suspended'),
            ['type' => 'block'],
        );
    }
    public static function deliveryManLoyaltyPoint(mixed $transactionId, string $description): array
    {
        return [
            'title' => translate('Loyalty point transaction'),
            'description' => $description,
            'data_id' => $transactionId,
            'image' => '',
            'type' => 'loyalty_point',
        ];
    }
    public static function deliveryManReferralUsed(): array
    {
        return [
            'title' => translate('New referral'),
            'description' => translate('Your referral code is used by new deliveryman. wait for their first order to get your rewards.'),
            'data_id' => '',
            'image' => '',
            'type' => 'deliveryman_referral',
        ];
    }
    public static function cashCollectedByAdmin(): array
    {
        return self::make(
            translate('Cash collected'),
            translate('messages.Your hand in cash has been collected by admin'),
            ['type' => 'cash_collect'],
        );
    }
    public static function suspendedForCashLimit(): array
    {
        return self::make(
            translate('messages.suspended'),
            translate('Your account has been temporarily suspended due to exceeding the cash limit'),
            ['type' => 'block'],
        );
    }
    public static function deliveryManUnassigned(): array
    {
        return self::make(
            translate('Order notification'),
            translate('messages.You are unassigned from a order'),
            ['type' => 'unassign'],
        );
    }
    public static function deliveryManAssigned($orderId): array
    {
        return self::make(
            translate('Order notification'),
            translate('messages.You are assigned to a order'),
            ['order_id' => $orderId, 'type' => 'order_status'],
        );
    }
}
