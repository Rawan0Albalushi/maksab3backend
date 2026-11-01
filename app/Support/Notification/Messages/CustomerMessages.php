<?php

namespace App\Support\Notification\Messages;

use App\Services\System\CurrencyService;

trait CustomerMessages
{
    public static function referralWalletCredit(mixed $amount, ?string $referredName, array $extra = []): array
    {
        return array_merge([
            'title' => translate('messages.Congratulation'),
            'description' => translate('You have received').' '.app(CurrencyService::class)->format($amount).' '.translate('in your wallet as').' '.$referredName.' '.translate('You referred completed their first order'),
        ], $extra, ['image' => '', 'type' => 'referral_code']);
    }
    public static function loyaltyPointsEarned(mixed $points, array $extra = []): array
    {
        return array_merge([
            'title' => translate('messages.Congratulation'),
            'description' => translate('You have received').' '.$points.' '.translate('Points as loyalty point'),
        ], $extra, ['image' => '', 'type' => 'loyalty_point']);
    }
    public static function cashbackCredited(mixed $amount, array $extra = []): array
    {
        return array_merge([
            'title' => translate('messages.Congratulations, you have received cashback').': '.$amount,
            'description' => translate('The cashback amount successfully added to your wallet'),
        ], $extra, ['image' => '', 'type' => 'cashback']);
    }
    public static function cashbackCreditedWithAmountPlaceholder(mixed $amount, array $extra = []): array
    {
        return array_merge([
            'title' => translate('messages.Congratulations, you have received cashback').': '.$amount,
            'description' => translate('The cashback amount successfully added to your wallet'),
        ], $extra, ['image' => '', 'type' => 'cashback']);
    }
    public static function referralCodeUsed(string $firstName, string $lastName): array
    {
        return self::make(
            translate('messages.Your referral code is used by').' '.$firstName.' '.$lastName,
            translate('Be prepare to receive when they complete there first purchase'),
            ['order_id' => 1, 'type' => 'referral_code'],
        );
    }
    public static function orderReadyOtp($order): array
    {
        return self::make(
            translate('messages.Order ready to be delivered'),
            translate('Your order is ready to be delivered, please share your OTP with deliveryman.').' '
                .translate('OTP').':'.$order->otp.', '.translate('Order ID').':'.$order->id,
            ['order_id' => $order?->id ?? '', 'type' => 'otp'],
        );
    }
    public static function parcelDeliveryChargeRefunded($order, bool $toWallet): array
    {
        return self::make(
            translate('Order refunded'),
            $toWallet
                ? translate('Your parcel\'s delivery charge has been refunded to your wallet')
                : translate('Your parcel\'s delivery charge has been marked as refunded'),
            ['order_id' => $order->id, 'type' => 'order_status', 'order_status' => $order->order_status],
        );
    }
    public static function proCustomerSubscription(string $title, string $description, string $type, mixed $dataId): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'image' => '',
            'type' => $type,
            'data_id' => (string) $dataId,
        ];
    }
    public static function fundAddedToWallet(): array
    {
        return self::make(
            translate('messages.Fund added'),
            translate('messages.Fund added to your wallet'),
            ['type' => 'add_fund', 'order_status' => ''],
        );
    }
    public static function orderRefundApproved($order): array
    {
        return self::make(
            translate('Order refunded'),
            translate('messages.Your refund request has been approved'),
            ['order_id' => $order->id, 'type' => 'order_status', 'order_status' => $order->order_status],
        );
    }
    public static function orderRefundRejected($order): array
    {
        return self::make(
            translate('Refund canceled'),
            translate('Your refund request has been rejected'),
            ['order_id' => $order->id, 'type' => 'order_status', 'order_status' => $order->order_status],
        );
    }
    public static function monthlyOrderReminder(string $title, string $body, mixed $orderId, mixed $dataId): array
    {
        return [
            'title' => $title,
            'description' => $body,
            'body' => $body,
            'image' => '',
            'type' => 'monthly_order_reminder',
            'order_id' => (string) $orderId,
            'data_id' => (string) $dataId,
        ];
    }
    public static function offlinePaymentInfoUpdated($order): array
    {
        return self::make(
            translate('Payment information'),
            translate('Updated successfully'),
            ['order_id' => $order->id, 'type' => 'order_status'],
        );
    }
    public static function posOrderWalletDebited($order): array
    {
        return self::make(
            app(CurrencyService::class)->format($order->order_amount).' '.translate('Amount is debited'),
            app(CurrencyService::class)->format($order->order_amount).' '.translate('Has been debited from your wallet balance for POS order ID').' '.$order->id,
            ['order_id' => $order->id, 'type' => 'add_fund'],
        );
    }
    public static function offlinePaymentApproved($order, mixed $description): array
    {
        return self::make(
            translate('Your offline payment is approved'),
            $description == false || $description == null ? ' ' : $description,
            ['order_id' => $order->id, 'type' => 'order_status'],
        );
    }
    public static function offlinePaymentRejected($order, mixed $description, mixed $note = null): array
    {
        return self::make(
            translate('Your offline payment was rejected'),
            $description ?? $note,
            ['order_id' => $order->id, 'type' => 'order_status'],
        );
    }
}
