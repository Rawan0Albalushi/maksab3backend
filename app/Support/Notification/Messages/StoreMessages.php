<?php

namespace App\Support\Notification\Messages;

trait StoreMessages
{
    public static function verifiedSeller(): array
    {
        return self::make(
            translate('verified'),
            translate('Congratulations! Your seller account is now verified.'),
            ['type' => 'verified_badge'],
        );
    }
    public static function verifiedSellerRemoved(): array
    {
        return self::make(
            translate('Removed'),
            translate('Your seller account is no longer verified.'),
            ['type' => 'verified_badge'],
        );
    }
    public static function bundleCreatedByAdmin(string $bundleName): array
    {
        return self::make(
            translate('messages.New bundle'),
            translate('messages.The admin has created a bundle for your store').': '.$bundleName,
            ['type' => 'bundle'],
        );
    }

    public static function zoneDeactivated(string $zoneName): array
    {
        return self::make(
            translate('messages.Zone deactivated'),
            translate('messages.Your store\'s zone has been deactivated and can no longer receive new orders').': '.$zoneName,
            ['type' => 'zone'],
        );
    }

    public static function storeAccountActivated(): array
    {
        return self::make(
            translate('Account activation'),
            translate('messages.Your account has been activated'),
            ['type' => 'unblock'],
        );
    }
    public static function productApproved(): array
    {
        return self::make(
            translate('Product approved'),
            translate('Product request has been approved by admin'),
            ['type' => 'product_approve', 'order_status' => ''],
        );
    }
    public static function productRejected(): array
    {
        return self::make(
            translate('Product rejected'),
            translate('Product request has been rejected by admin'),
            ['type' => 'product_rejected', 'order_status' => ''],
        );
    }
    public static function advertisementCreatedByAdmin($advertisement): array
    {
        return self::make(
            translate('New advertisement'),
            translate('Admin has added a new advertisement for your store'),
            ['type' => 'advertisement', 'advertisement_id' => $advertisement->id, 'order_status' => ''],
        );
    }
    public static function advertisementApproved($advertisement): array
    {
        return self::make(
            translate('Advertisement approved'),
            translate('Admin has approved your advertisement'),
            ['type' => 'advertisement', 'advertisement_id' => $advertisement->id, 'order_status' => ''],
        );
    }
    public static function advertisementNotice($advertisement, mixed $title, mixed $description): array
    {
        return self::make(
            $title,
            $description,
            ['type' => 'advertisement', 'advertisement_id' => $advertisement->id, 'order_status' => ''],
        );
    }
    public static function campaignNotice($campaign, mixed $title, mixed $description): array
    {
        return self::make(
            $title,
            $description,
            ['type' => 'campaign', 'data_id' => $campaign->id, 'order_status' => ''],
        );
    }
    public static function subscriptionCanceled(): array
    {
        return self::make(
            translate('Subscription canceled'),
            translate('Your subscription has been canceled'),
            ['type' => 'subscription', 'order_status' => ''],
        );
    }
    public static function subscriptionPlanUpdated(): array
    {
        return self::make(
            translate('Subscription plan updated'),
            translate('Your subscription plan has been updated'),
            ['type' => 'subscription', 'order_status' => ''],
        );
    }
    public static function subscriptionSuccessful(): array
    {
        return self::make(
            translate('Subscription successful'),
            translate('You are successfully subscribed'),
            ['type' => 'subscription', 'order_status' => ''],
        );
    }
    public static function storeSubscription(string $title, string $description): array
    {
        return self::make($title, $description, ['type' => 'subscription', 'order_status' => '']);
    }
    public static function storeSubscriptionCanceled(): array
    {
        return self::make(
            translate('Subscription canceled'),
            translate('Your subscription has been canceled'),
            ['type' => 'subscription', 'order_status' => ''],
        );
    }
}
