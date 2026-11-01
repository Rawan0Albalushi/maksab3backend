<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * notification_settings.title and .sub_title are translation keys, rendered as
 * translate($item->title). They were seeded with underscore forms; the language
 * keys are now the readable spaced forms, so existing rows no longer resolve and
 * would re-add underscore keys to resources/lang on every page view.
 *
 * The `key` column is the functional identifier and is deliberately untouched.
 */
return new class extends Migration
{
    /** @var array<string,string> old display string => current language key */
    private array $map = [
            'forget_password' => 'Forget Password',
            'Sent_notification_on_forget_password' => 'Sent notification on forget password',
            'deliveryman_self_registration' => 'Deliveryman self registration',
            'Sent_notification_on_deliveryman_self_registration' => 'Sent notification on deliveryman self registration',
            'store_self_registration' => 'Store self registration',
            'Sent_notification_on_store_self_registration' => 'Sent notification on store self registration',
            'campaign_join_request' => 'Campaign Join Request',
            'Sent_notification_on_campaign_join_request' => 'Sent notification on campaign join request',
            'withdraw_request' => 'Withdraw Request',
            'Sent_notification_on_withdraw_request' => 'Sent notification on withdraw request',
            'order_refund_request' => 'Order Refund Request',
            'Sent_notification_on_order_refund_request' => 'Sent notification on order refund request',
            'advertisement_add' => 'Advertisement add',
            'Sent_notification_on_advertisement_add' => 'Sent notification on advertisement add',
            'advertisement_update' => 'Advertisement update',
            'Sent_notification_on_advertisement_update' => 'Sent notification on advertisement update',
            'deliveryman_registration' => 'Deliveryman registration',
            'Sent_notification_on_deliveryman_registration' => 'Sent notification on deliveryman registration',
            'deliveryman_registration_approval' => 'Deliveryman registration approval',
            'Sent_notification_on_deliveryman_registration_approval' => 'Sent notification on deliveryman registration approval',
            'deliveryman_registration_deny' => 'Deliveryman registration deny',
            'Sent_notification_on_deliveryman_registration_deny' => 'Sent notification on deliveryman registration deny',
            'deliveryman_account_block' => 'Deliveryman account block',
            'Sent_notification_on_deliveryman_account_block' => 'Sent notification on deliveryman account block',
            'deliveryman_account_unblock' => 'Deliveryman account unblock',
            'Sent_notification_on_deliveryman_account_unblock' => 'Sent notification on deliveryman account unblock',
            'deliveryman_forget_password' => 'Deliveryman forget password',
            'Sent_notification_on_deliveryman_forget_password' => 'Sent notification on deliveryman forget password',
            'deliveryman_collect_cash' => 'Deliveryman collect cash',
            'Sent_notification_on_deliveryman_collect_cash' => 'Sent notification on deliveryman collect cash',
            'deliveryman_order_notification' => 'Deliveryman order notification',
            'Sent_notification_order_notification_to_deliveryman' => 'Sent notification order notification to deliveryman',
            'deliveryman_order_assign_or_unassign' => 'Deliveryman order assign or unassign',
            'Sent_notification_on_deliveryman_order_assign_or_unassign' => 'Sent notification on deliveryman order assign or unassign',
            'store_registration' => 'Store Registration',
            'Sent_notification_on_store_registration' => 'Sent notification on store registration',
            'store_registration_approval' => 'Store registration approval',
            'Sent_notification_on_store_registration_approval' => 'Sent notification on store registration approval',
            'store_registration_deny' => 'Store registration deny',
            'Sent_notification_on_store_registration_deny' => 'Sent notification on store registration deny',
            'store_account_block' => 'Store account block',
            'Sent_notification_on_store_account_block' => 'Sent notification on store account block',
            'store_account_unblock' => 'Store account unblock',
            'Sent_notification_on_store_account_unblock' => 'Sent notification on store account unblock',
            'store_withdraw_approve' => 'Store withdraw approve',
            'Sent_notification_on_store_withdraw_approve' => 'Sent notification on store withdraw approve',
            'store_withdraw_rejaction' => 'Store withdraw rejection',
            'Sent_notification_on_store_withdraw_rejaction' => 'Sent notification on store withdraw rejection',
            'store_campaign_join_request' => 'Store campaign join request',
            'Sent_notification_on_store_campaign_join_request' => 'Sent notification on store campaign join request',
            'store_campaign_join_rejaction' => 'Store campaign join rejection',
            'Sent_notification_on_store_campaign_join_rejaction' => 'Sent notification on store campaign join rejection',
            'store_campaign_join_approval' => 'Store campaign join approval',
            'Sent_notification_on_store_campaign_join_approval' => 'Sent notification on store campaign join approval',
            'store_order_notification' => 'Store order notification',
            'Sent_notification_on_store_order_notification' => 'Sent notification on store order notification',
            'store_product_approve' => 'Store product approve',
            'Sent_notification_on_store_product_approve' => 'Sent notification on store product approve',
            'store_product_reject' => 'Store product reject',
            'Sent_notification_on_store_product_reject' => 'Sent notification on store product reject',
            'store_subscription_success' => 'Store subscription success',
            'Sent_notification_on_store_subscription_success' => 'Sent notification on store subscription success',
            'store_subscription_renew' => 'Store subscription renew',
            'Sent_notification_on_store_subscription_renew' => 'Sent notification on store subscription renew',
            'store_subscription_shift' => 'Store subscription shift',
            'Sent_notification_on_store_subscription_shift' => 'Sent notification on store subscription shift',
            'store_subscription_cancel' => 'Store subscription cancel',
            'Sent_notification_on_store_subscription_cancel' => 'Sent notification on store subscription cancel',
            'store_subscription_plan_update' => 'Store subscription plan update',
            'Sent_notification_on_store_subscription_plan_update' => 'Sent notification on store subscription plan update',
            'store_advertisement_create_by_admin' => 'Store advertisement create by admin',
            'Sent_notification_on_store_advertisement_create_by_admin' => 'Sent notification on store advertisement create by admin',
            'store_advertisement_approval' => 'Store advertisement approval',
            'Sent_notification_on_store_advertisement_approval' => 'Sent notification on store advertisement approval',
            'store_advertisement_deny' => 'Store advertisement deny',
            'Sent_notification_on_store_advertisement_deny' => 'Sent notification on store advertisement deny',
            'store_advertisement_resume' => 'Store advertisement resume',
            'Sent_notification_on_store_advertisement_resume' => 'Sent notification on store advertisement resume',
            'store_advertisement_pause' => 'Store advertisement pause',
            'Sent_notification_on_store_advertisement_pause' => 'Sent notification on store advertisement pause',
            'customer_registration' => 'Customer Registration',
            'Sent_notification_on_customer_registration' => 'Sent notification on customer registration',
            'customer_pos_registration' => 'Customer pos registration',
            'Sent_notification_on_customer_pos_registration' => 'Sent notification on customer pos registration',
            'customer_order_notification' => 'Customer order notification',
            'Sent_notification_on_customer_order_notification' => 'Sent notification on customer order notification',
            'customer_delivery_verification' => 'Customer delivery verification',
            'Sent_notification_on_customer_delivery_verification' => 'Sent notification on customer delivery verification',
            'customer_refund_request_approval' => 'Customer refund request approval',
            'Sent_notification_on_customer_refund_request_approval' => 'Sent notification on customer refund request approval',
            'customer_refund_request_rejaction' => 'Customer refund request rejection',
            'Sent_notification_on_customer_refund_request_rejaction' => 'Sent notification on customer refund request rejection',
            'customer_add_fund_to_wallet' => 'Customer add fund to wallet',
            'Sent_notification_on_customer_add_fund_to_wallet' => 'Sent notification on customer add fund to wallet',
            'customer_offline_payment_approve' => 'Customer offline payment approve',
            'Sent_notification_on_customer_offline_payment_approve' => 'Sent notification on customer offline payment approve',
            'customer_offline_payment_deny' => 'Customer offline payment deny',
            'Sent_notification_on_customer_offline_payment_deny' => 'Sent notification on customer offline payment deny',
            'customer_account_block' => 'Customer account block',
            'Sent_notification_on_customer_account_block' => 'Sent notification on customer account block',
            'customer_account_unblock' => 'Customer account unblock',
            'Sent_notification_on_customer_account_unblock' => 'Sent notification on customer account unblock',
            'customer_cashback' => 'Customer cashback',
            'Sent_notification_on_customer_cashback' => 'Sent notification on customer cashback',
            'customer_referral_bonus_earning' => 'Customer referral bonus earning',
            'Sent_notification_on_customer_referral_bonus_earning' => 'Sent notification on customer referral bonus earning',
            'customer_new_referral_join' => 'Customer new referral join',
            'Sent_notification_on_customer_new_referral_join' => 'Sent notification on customer new referral join',
            'account_block' => 'Account block',
            'Get_notification_on_account_block' => 'Get notification on account block',
            'account_unblock' => 'Account unblock',
            'Get_notification_on_account_unblock' => 'Get notification on account unblock',
            'withdraw_approve' => 'Withdraw approve',
            'Get_notification_on_withdraw_approve' => 'Get notification on withdraw approve',
            'withdraw_rejaction' => 'Withdraw Rejection',
            'Get_notification_on_withdraw_rejaction' => 'Get notification on withdraw rejection',
            'Get_notification_on_campaign_join_request' => 'Get notification on campaign join request',
            'campaign_join_rejaction' => 'Campaign Join Rejection',
            'Get_notification_on_campaign_join_rejaction' => 'Get notification on campaign join rejection',
            'campaign_join_approval' => 'Campaign Join Approval',
            'Get_notification_on_campaign_join_approval' => 'Get notification on campaign join approval',
            'order_notification' => 'Order Notification',
            'Get_notification_on_order_notification' => 'Get notification on order notification',
            'advertisement_create_by_admin' => 'Advertisement Create By Admin',
            'Get_notification_on_advertisement_create_by_admin' => 'Get notification on advertisement create by admin',
            'advertisement_approval' => 'Advertisement Approval',
            'Get_notification_on_advertisement_approval' => 'Get notification on advertisement approval',
            'advertisement_deny' => 'Advertisement Deny',
            'Get_notification_on_advertisement_deny' => 'Get notification on advertisement deny',
            'advertisement_resume' => 'Advertisement Resume',
            'Get_notification_on_advertisement_resume' => 'Get notification on advertisement resume',
            'advertisement_pause' => 'Advertisement Pause',
            'Get_notification_on_advertisement_pause' => 'Get notification on advertisement pause',
            'product_approve' => 'Product approve',
            'Get_notification_on_product_approve' => 'Get notification on product approve',
            'product_reject' => 'Product reject',
            'Get_notification_on_product_reject' => 'Get notification on product reject',
            'subscription_success' => 'Subscription success',
            'Get_notification_on_subscription_success' => 'Get notification on subscription success',
            'subscription_renew' => 'Subscription Renew',
            'Get_notification_on_subscription_renew' => 'Get notification on subscription renew',
            'subscription_shift' => 'Subscription Shift',
            'Get_notification_on_subscription_shift' => 'Get notification on subscription shift',
            'subscription_cancel' => 'Subscription Cancel',
            'Get_notification_on_subscription_cancel' => 'Get notification on subscription cancel',
            'subscription_plan_update' => 'Subscription plan update',
            'Get_notification_on_subscription_plan_update' => 'Get notification on subscription plan update',
            'customer_pos_order_wallet_notification' => 'Customer pos order wallet notification',
            'Sent_notification_on_wallet_payment_on_POS' => 'Sent notification on wallet payment on POS',
            'customer_loyalty_point_earning' => 'Customer loyalty point earning',
            'Sent_notification_on_loyalty_point_earning' => 'Sent notification on loyalty point earning',
            'customer_delivery_verification_otp' => 'Customer delivery verification otp',
            'Sent_customer_delivery_verification_otp' => 'Sent customer delivery verification otp',
            'deliveryman_withdraw_approve' => 'Deliveryman withdraw approve',
            'Sent_notification_on_deliveryman_withdraw_approve' => 'Sent notification on deliveryman withdraw approve',
            'deliveryman_withdraw_rejaction' => 'Deliveryman withdraw rejection',
            'Sent_notification_on_deliveryman_withdraw_rejaction' => 'Sent notification on deliveryman withdraw rejection',
            'deliveryman_loyalty_point_transaction' => 'Deliveryman loyalty point transaction',
            'Sent_notification_on_deliveryman_loyalty_point_transaction' => 'Sent notification on deliveryman loyalty point transaction',
            'deliveryman_referral_notification' => 'Deliveryman referral notification',
            'Sent_notification_on_deliveryman_referral_notification' => 'Sent notification on deliveryman referral notification',
            'deliveryman_referral_bonus' => 'Deliveryman referral bonus',
            'Sent_notification_on_deliveryman_referral_bonus' => 'Sent notification on deliveryman referral bonus',
            'deliveryman_withdraw_request' => 'Deliveryman Withdraw Request',
            'customer_forget_password' => 'Customer forget password',
            'Sent_notification_on_customer_forget_password' => 'Sent notification on customer forget password',
            'customer_registration_otp' => 'Customer registration otp',
            'Sent_notification_on_customer_registration_otp' => 'Sent notification on customer registration otp',
            'customer_login_otp' => 'Customer login otp',
            'Sent_notification_on_customer_login_otp' => 'Sent notification on customer login otp',
            'provider_registration' => 'Provider Registration',
            'Sent_notification_on_provider_self_registration' => 'Sent notification on provider self registration',
            'provider_withdraw_request' => 'Provider Withdraw Request',
            'Sent_notification_on_provider_withdraw_request' => 'Sent notification on provider withdraw request',
            'Sent_notification_on_provider_registration' => 'Sent notification on provider registration',
            'provider_registration_approval' => 'Provider registration approval',
            'Sent_notification_on_provider_registration_approval' => 'Sent notification on provider registration approval',
            'provider_registration_deny' => 'Provider registration deny',
            'Sent_notification_on_provider_registration_deny' => 'Sent notification on provider registration deny',
            'provider_account_block' => 'Provider account block',
            'Sent_notification_on_provider_account_block' => 'Sent notification on provider account block',
            'provider_account_unblock' => 'Provider account unblock',
            'Sent_notification_on_provider_account_unblock' => 'Sent notification on provider account unblock',
            'provider_withdraw_approve' => 'Provider withdraw approve',
            'Sent_notification_on_provider_withdraw_approve' => 'Sent notification on provider withdraw approve',
            'provider_withdraw_rejaction' => 'Provider withdraw rejection',
            'Sent_notification_on_provider_withdraw_rejaction' => 'Sent notification on provider withdraw rejection',
            'provider_trip_notification' => 'Provider trip notification',
            'Sent_notification_on_provider_trip_notification' => 'Sent notification on provider trip notification',
            'provider_subscription_success' => 'Provider subscription success',
            'Sent_notification_on_provider_subscription_success' => 'Sent notification on provider subscription success',
            'provider_subscription_renew' => 'Provider subscription renew',
            'Sent_notification_on_provider_subscription_renew' => 'Sent notification on provider subscription renew',
            'provider_subscription_shift' => 'Provider subscription shift',
            'Sent_notification_on_provider_subscription_shift' => 'Sent notification on provider subscription shift',
            'provider_subscription_cancel' => 'Provider subscription cancel',
            'Sent_notification_on_provider_subscription_cancel' => 'Sent notification on provider subscription cancel',
            'provider_subscription_plan_update' => 'Provider subscription plan update',
            'Sent_notification_on_provider_subscription_plan_update' => 'Sent notification on provider subscription plan update',
            'customer_trip_notification' => 'Customer trip notification',
            'Sent_notification_on_customer_trip_notification' => 'Sent notification on customer trip notification',
            'provider_booking_notification' => 'Provider booking notification',
            'Sent_notification_on_provider_booking_notification' => 'Sent notification on provider booking notification',
            'customer_booking_notification' => 'Customer booking notification',
            'Sent_notification_on_customer_booking_notification' => 'Sent notification on customer booking notification',
            'Forget Password' => 'Forgot Password',
            'Advertisement Deny' => 'Advertisement Denied',
    ];

    private array $tables = ['notification_settings', 'store_notification_settings'];

    public function up(): void
    {
        $this->rename($this->map);
    }

    public function down(): void
    {
        $this->rename(array_flip($this->map));
    }

    private function rename(array $map): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (['title', 'sub_title'] as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                foreach ($map as $from => $to) {
                    DB::table($table)->where($column, $from)->update([$column => $to]);
                }
            }
        }
    }
};
