<?php

namespace App\Traits\Notification;

use App\Services\System\NotificationSettingService;


trait NotificationDataSetUpTrait
{
    public static function getAdminNotificationSetupData(): array
    {
        $data[] = self::typeRow('Forgot Password', 'forget_password', 'admin', 'Sent notification on forget password', sms: 'active', push: 'disable');
        $data[] = self::typeRow(
            'Deliveryman self registration',
            'deliveryman_self_registration',
            'admin',
            'Sent notification on deliveryman self registration',
            push: 'disable',
        );
        $data[] = self::typeRow('Store self registration', 'store_self_registration', 'admin', 'Sent notification on store self registration', push: 'disable');
        $data[] = self::typeRow('Campaign Join Request', 'campaign_join_request', 'admin', 'Sent notification on campaign join request', push: 'disable');
        $data[] = self::typeRow('Withdraw Request', 'withdraw_request', 'admin', 'Sent notification on withdraw request', push: 'disable');
        $data[] = self::typeRow('Order Refund Request', 'order_refund_request', 'admin', 'Sent notification on order refund request', push: 'disable');

        $data[] = self::typeRow('Advertisement add', 'advertisement_add', 'admin', 'Sent notification on advertisement add', push: 'disable');
        $data[] = self::typeRow('Advertisement update', 'advertisement_update', 'admin', 'Sent notification on advertisement update', push: 'disable');


        $data[] = self::typeRow(
            'Deliveryman registration',
            'deliveryman_registration',
            'deliveryman',
            'Sent notification on deliveryman registration',
            push: 'disable',
        );
        $data[] = self::typeRow(
            'Deliveryman registration approval',
            'deliveryman_registration_approval',
            'deliveryman',
            'Sent notification on deliveryman registration approval',
            push: 'disable',
        );
        $data[] = self::typeRow(
            'Deliveryman registration deny',
            'deliveryman_registration_deny',
            'deliveryman',
            'Sent notification on deliveryman registration deny',
            push: 'disable',
        );
        $data[] = self::typeRow('Deliveryman account block', 'deliveryman_account_block', 'deliveryman', 'Sent notification on deliveryman account block');
        $data[] = self::typeRow('Deliveryman account unblock', 'deliveryman_account_unblock', 'deliveryman', 'Sent notification on deliveryman account unblock');
        $data[] = self::typeRow(
            'Deliveryman forget password',
            'deliveryman_forget_password',
            'deliveryman',
            'Sent notification on deliveryman forget password',
            sms: 'active',
            push: 'disable',
        );
        $data[] = self::typeRow('Deliveryman collect cash', 'deliveryman_collect_cash', 'deliveryman', 'Sent notification on deliveryman collect cash');

        $data[] = self::typeRow(
            'Deliveryman order notification',
            'deliveryman_order_notification',
            'deliveryman',
            'Sent notification order notification to deliveryman',
            mail: 'disable',
        );
        $data[] = self::typeRow(
            'Deliveryman order assign or unassign',
            'deliveryman_order_assign_unassign',
            'deliveryman',
            'Sent notification on deliveryman order assign or unassign',
            mail: 'disable',
        );




        $data[] = self::typeRow('Store Registration', 'store_registration', 'store', 'Sent notification on store registration', push: 'disable');
        $data[] = self::typeRow(
            'Store registration approval',
            'store_registration_approval',
            'store',
            'Sent notification on store registration approval',
            push: 'disable',
        );
        $data[] = self::typeRow('Store registration deny', 'store_registration_deny', 'store', 'Sent notification on store registration deny', push: 'disable');
        $data[] = self::typeRow('Store account block', 'store_account_block', 'store', 'Sent notification on store account block');
        $data[] = self::typeRow('Store account unblock', 'store_account_unblock', 'store', 'Sent notification on store account unblock');
        $data[] = self::typeRow('Store withdraw approve', 'store_withdraw_approve', 'store', 'Sent notification on store withdraw approve');
        $data[] = self::typeRow('Store withdraw rejection', 'store_withdraw_rejaction', 'store', 'Sent notification on store withdraw rejection');
        $data[] = self::typeRow(
            'Store campaign join request',
            'store_campaign_join_request',
            'store',
            'Sent notification on store campaign join request',
            push: 'disable',
        );
        $data[] = self::typeRow('Store campaign join rejection', 'store_campaign_join_rejaction', 'store', 'Sent notification on store campaign join rejection');
        $data[] = self::typeRow('Store campaign join approval', 'store_campaign_join_approval', 'store', 'Sent notification on store campaign join approval');
        $data[] = self::typeRow('Store order notification', 'store_order_notification', 'store', 'Sent notification on store order notification', mail: 'disable');

        $data[] = self::typeRow('Store product approve', 'store_product_approve', 'store', 'Sent notification on store product approve');
        $data[] = self::typeRow('Store product reject', 'store_product_reject', 'store', 'Sent notification on store product reject');
        $data[] = self::typeRow('Store subscription success', 'store_subscription_success', 'store', 'Sent notification on store subscription success');
        $data[] = self::typeRow('Store subscription renew', 'store_subscription_renew', 'store', 'Sent notification on store subscription renew');
        $data[] = self::typeRow('Store subscription shift', 'store_subscription_shift', 'store', 'Sent notification on store subscription shift');
        $data[] = self::typeRow('Store subscription cancel', 'store_subscription_cancel', 'store', 'Sent notification on store subscription cancel');
        $data[] = self::typeRow(
            'Store subscription plan update',
            'store_subscription_plan_update',
            'store',
            'Sent notification on store subscription plan update',
            push: 'inactive',
        );


        $data[] = self::typeRow(
            'Store advertisement create by admin',
            'store_advertisement_create_by_admin',
            'store',
            'Sent notification on store advertisement create by admin',
        );
        $data[] = self::typeRow('Store advertisement approval', 'store_advertisement_approval', 'store', 'Sent notification on store advertisement approval');
        $data[] = self::typeRow('Store advertisement deny', 'store_advertisement_deny', 'store', 'Sent notification on store advertisement deny');
        $data[] = self::typeRow('Store advertisement resume', 'store_advertisement_resume', 'store', 'Sent notification on store advertisement resume');
        $data[] = self::typeRow('Store advertisement pause', 'store_advertisement_pause', 'store', 'Sent notification on store advertisement pause');

        $data[] = self::typeRow('Customer Registration', 'customer_registration', 'customer', 'Sent notification on customer registration', push: 'disable');
        $data[] = self::typeRow(
            'Customer pos registration',
            'customer_pos_registration',
            'customer',
            'Sent notification on customer pos registration',
            push: 'disable',
        );

        $data[] = self::typeRow('Customer order notification', 'customer_order_notification', 'customer', 'Sent notification on customer order notification');

        $data[] = self::typeRow(
            'Customer delivery verification',
            'customer_delivery_verification',
            'customer',
            'Sent notification on customer delivery verification',
        );

        $data[] = self::typeRow(
            'Customer refund request approval',
            'customer_refund_request_approval',
            'customer',
            'Sent notification on customer refund request approval',
        );
        $data[] = self::typeRow(
            'Customer refund request rejection',
            'customer_refund_request_rejaction',
            'customer',
            'Sent notification on customer refund request rejection',
        );
        $data[] = self::typeRow('Customer add fund to wallet', 'customer_add_fund_to_wallet', 'customer', 'Sent notification on customer add fund to wallet');
        $data[] = self::typeRow(
            'Customer offline payment approve',
            'customer_offline_payment_approve',
            'customer',
            'Sent notification on customer offline payment approve',
        );
        $data[] = self::typeRow('Customer offline payment deny', 'customer_offline_payment_deny', 'customer', 'Sent notification on customer offline payment deny');
        $data[] = self::typeRow('Customer account block', 'customer_account_block', 'customer', 'Sent notification on customer account block');
        $data[] = self::typeRow('Customer account unblock', 'customer_account_unblock', 'customer', 'Sent notification on customer account unblock');
        $data[] = self::typeRow('Customer cashback', 'customer_cashback', 'customer', 'Sent notification on customer cashback', mail: 'disable');
        $data[] = self::typeRow(
            'Customer referral bonus earning',
            'customer_referral_bonus_earning',
            'customer',
            'Sent notification on customer referral bonus earning',
            mail: 'disable',
        );
        $data[] = self::typeRow(
            'Customer new referral join',
            'customer_new_referral_join',
            'customer',
            'Sent notification on customer new referral join',
            mail: 'disable',
        );

        // The promotion broadcasts, mirroring 2026_09_07_100002. One toggle per promotion, not
        // one per enrolment transition: a customer sees a single event -- an offer they can buy
        // appeared -- where a vendor sees the whole conversation.
        //
        // Mail off. The copy has a shelf life measured in hours, and a customer reading it
        // tomorrow reads about an offer that has moved on.
        $data[] = self::typeRow(
            'Customer BOGO Offer',
            'customer_bogo_offer',
            'customer',
            'Sent to customers in the zone when a BOGO offer goes live at a store',
            mail: 'disable',
        );
        $data[] = self::typeRow(
            'Customer Happy Hour Offer',
            'customer_happy_hour_offer',
            'customer',
            'Sent to customers in the zone when a Happy Hour offer goes live at a store',
            mail: 'disable',
        );

        return $data;
    }
    public static function getStoreNotificationSetupData($id): array
    {
        $data[] = self::storeRow('Account block', 'store_account_block', $id, 'Get notification on account block');
        $data[] = self::storeRow('Account unblock', 'store_account_unblock', $id, 'Get notification on account unblock');
        $data[] = self::storeRow('Withdraw approve', 'store_withdraw_approve', $id, 'Get notification on withdraw approve');
        $data[] = self::storeRow('Withdraw Rejection', 'store_withdraw_rejaction', $id, 'Get notification on withdraw rejection');
        $data[] = self::storeRow('Campaign Join Request', 'store_campaign_join_request', $id, 'Get notification on campaign join request', push: 'disable');
        $data[] = self::storeRow('Campaign Join Rejection', 'store_campaign_join_rejaction', $id, 'Get notification on campaign join rejection');
        $data[] = self::storeRow('Campaign Join Approval', 'store_campaign_join_approval', $id, 'Get notification on campaign join approval');
        $data[] = self::storeRow('Order Notification', 'store_order_notification', $id, 'Get notification on order notification', mail: 'disable');

        // The two promotion toggles. Their keys carry no `store_` prefix because that is how
        // 2026_09_02_100014 seeded the matching notification_settings rows, and NotificationGate
        // matches a store row to the global one by key alone -- renaming here would simply stop
        // them pairing up.
        //
        // Without these rows the per-store gate returns 0 for every promotion transition, so no
        // store ever receives a push or a mail about an enrolment however the global settings are
        // configured. The gate installs this whole list when it misses a key, so adding them here
        // backfills existing stores on first use as well as covering new ones.
        //
        // Push on, mail off, matching that migration: an enrolment decision is time-sensitive and
        // belongs in the app, while an email for every join request would be noise.
        $data[] = self::storeRow('BOGO Offer Enrollment', 'bogo_offer_enrollment', $id, 'Get notification when a BOGO offer enrolment is invited, approved or rejected', mail: 'disable');
        $data[] = self::storeRow('Happy Hour Enrollment', 'happy_hour_enrollment', $id, 'Get notification when a Happy Hour enrolment is invited, approved or rejected', mail: 'disable');

        $data[] = self::storeRow('Advertisement Create By Admin', 'store_advertisement_create_by_admin', $id, 'Get notification on advertisement create by admin');
        $data[] = self::storeRow('Advertisement Approval', 'store_advertisement_approval', $id, 'Get notification on advertisement approval');
        $data[] = self::storeRow('Advertisement Denied', 'store_advertisement_deny', $id, 'Get notification on advertisement deny');
        $data[] = self::storeRow('Advertisement Resume', 'store_advertisement_resume', $id, 'Get notification on advertisement resume');
        $data[] = self::storeRow('Advertisement Pause', 'store_advertisement_pause', $id, 'Get notification on advertisement pause');

        $data[] = self::storeRow('Product approve', 'store_product_approve', $id, 'Get notification on product approve');
        $data[] = self::storeRow('Product reject', 'store_product_reject', $id, 'Get notification on product reject');
        $data[] = self::storeRow('Subscription success', 'store_subscription_success', $id, 'Get notification on subscription success');
        $data[] = self::storeRow('Subscription Renew', 'store_subscription_renew', $id, 'Get notification on subscription renew');
        $data[] = self::storeRow('Subscription Shift', 'store_subscription_shift', $id, 'Get notification on subscription shift');
        $data[] = self::storeRow('Subscription Cancel', 'store_subscription_cancel', $id, 'Get notification on subscription cancel');
        $data[] = self::storeRow('Subscription plan update', 'store_subscription_plan_update', $id, 'Get notification on subscription plan update');

        return $data;
    }


    public static function updateAdminNotificationSetupData()
    {
        $rows = [
            ['deliveryman_forget_password', 'deliveryman', 'disable'],
        ];

        foreach ($rows as [$key, $type, $pushStatus]) {
            app(NotificationSettingService::class)->updatePushStatus($key, $type, $pushStatus);
        }

        return true;
    }
    public static function addNewAdminNotificationSetupData(){

        $data[] = self::typeRow(
            'Customer pos order wallet notification',
            'customer_pos_order_wallet_notification',
            'customer',
            'Sent notification on wallet payment on POS',
            mail: 'disable',
        );

        $data[] = self::typeRow(
            'Customer loyalty point earning',
            'customer_loyalty_point_earning',
            'customer',
            'Sent notification on loyalty point earning',
            mail: 'disable',
        );
        $data[] = self::typeRow(
            'Customer delivery verification otp',
            'customer_delivery_verification_otp',
            'customer',
            'Sent customer delivery verification otp',
            mail: 'disable',
            sms: 'inactive',
            push: 'disable',
        );

        $data[] = self::typeRow('Deliveryman withdraw approve', 'deliveryman_withdraw_approve', 'deliveryman', 'Sent notification on deliveryman withdraw approve');
        $data[] = self::typeRow(
            'Deliveryman withdraw rejection',
            'deliveryman_withdraw_rejaction',
            'deliveryman',
            'Sent notification on deliveryman withdraw rejection',
        );
        $data[] = self::typeRow(
            'Deliveryman loyalty point transaction',
            'deliveryman_loyalty_point_transaction',
            'deliveryman',
            'Sent notification on deliveryman loyalty point transaction',
            mail: 'disable',
        );
        $data[] = self::typeRow(
            'Deliveryman referral notification',
            'deliveryman_referral_notification',
            'deliveryman',
            'Sent notification on deliveryman referral notification',
            mail: 'disable',
        );
        $data[] = self::typeRow(
            'Deliveryman referral bonus',
            'deliveryman_referral_bonus',
            'deliveryman',
            'Sent notification on deliveryman referral bonus',
            mail: 'disable',
        );
        $data[] = self::typeRow('Deliveryman Withdraw Request', 'dm_withdraw_request', 'admin', 'Sent notification on withdraw request', push: 'disable');

            self::checkAndUpdateAdminNotificationData($data);
            self::deleteAdminNotificationSetupData();
            return true;
    }
    public static function deleteAdminNotificationSetupData()
    {
        $rows = [
            ['customer_forget_password', 'customer'],
            ['customer_registration_otp', 'customer'],
            ['customer_login_otp', 'customer'],
        ];

        foreach ($rows as [$key, $type]) {
            app(NotificationSettingService::class)->deleteByKeyAndType($key, $type);
        }

        return true;
    }

    public static function checkAndUpdateAdminNotificationData($data){
        foreach($data as $item){
            if(app(NotificationSettingService::class)->missingForModule($item['key'], $item['type'], data_get($item,'module_type','all'))){
                $notificationsetting = app(NotificationSettingService::class)->findOrNewForModule($item['key'], $item['type'], data_get($item,'module_type','all'));
                $notificationsetting->title = $item['title'];
                $notificationsetting->sub_title = $item['sub_title'];
                $notificationsetting->mail_status = $item['mail_status'];
                $notificationsetting->sms_status = $item['sms_status'];
                $notificationsetting->push_notification_status = $item['push_notification_status'];
                $notificationsetting->module_type = data_get($item,'module_type','all');
                $notificationsetting->save();
            }
        }
        return true;
    }
    public static function rentalAdminNotificationRows(): array
    {
        $data[] = self::typeRow(
            'Provider Registration',
            'provider_self_registration',
            'admin',
            'Sent notification on provider self registration',
            push: 'disable',
            module: 'rental',
        );
        $data[] = self::typeRow(
            'Provider Withdraw Request',
            'provider_withdraw_request',
            'admin',
            'Sent notification on provider withdraw request',
            push: 'disable',
            module: 'rental',
        );

        $data[] = self::typeRow(
            'Provider Registration',
            'provider_registration',
            'provider',
            'Sent notification on provider registration',
            push: 'disable',
            module: 'rental',
        );
        $data[] = self::typeRow(
            'Provider registration approval',
            'provider_registration_approval',
            'provider',
            'Sent notification on provider registration approval',
            push: 'disable',
            module: 'rental',
        );
        $data[] = self::typeRow(
            'Provider registration deny',
            'provider_registration_deny',
            'provider',
            'Sent notification on provider registration deny',
            push: 'disable',
            module: 'rental',
        );
        $data[] = self::typeRow('Provider account block', 'provider_account_block', 'provider', 'Sent notification on provider account block', module: 'rental');
        $data[] = self::typeRow(
            'Provider account unblock',
            'provider_account_unblock',
            'provider',
            'Sent notification on provider account unblock',
            module: 'rental',
        );
        $data[] = self::typeRow(
            'Provider withdraw approve',
            'provider_withdraw_approve',
            'provider',
            'Sent notification on provider withdraw approve',
            module: 'rental',
        );
        $data[] = self::typeRow(
            'Provider withdraw rejection',
            'provider_withdraw_rejaction',
            'provider',
            'Sent notification on provider withdraw rejection',
            module: 'rental',
        );
        $data[] = self::typeRow(
            'Provider trip notification',
            'provider_trip_notification',
            'provider',
            'Sent notification on provider trip notification',
            mail: 'disable',
            module: 'rental',
        );
        $data[] = self::typeRow(
            'Provider subscription success',
            'provider_subscription_success',
            'provider',
            'Sent notification on provider subscription success',
            module: 'rental',
        );
        $data[] = self::typeRow(
            'Provider subscription renew',
            'provider_subscription_renew',
            'provider',
            'Sent notification on provider subscription renew',
            module: 'rental',
        );
        $data[] = self::typeRow(
            'Provider subscription shift',
            'provider_subscription_shift',
            'provider',
            'Sent notification on provider subscription shift',
            module: 'rental',
        );
        $data[] = self::typeRow(
            'Provider subscription cancel',
            'provider_subscription_cancel',
            'provider',
            'Sent notification on provider subscription cancel',
            module: 'rental',
        );
        $data[] = self::typeRow(
            'Provider subscription plan update',
            'provider_subscription_plan_update',
            'provider',
            'Sent notification on provider subscription plan update',
            push: 'inactive',
            module: 'rental',
        );

        $data[] = self::typeRow(
            'Customer trip notification',
            'customer_trip_notification',
            'customer',
            'Sent notification on customer trip notification',
            module: 'rental',
        );

        return $data;
    }
    public static function serviceAdminNotificationRows(): array
    {
        $data[] = self::typeRow(
            'Provider Registration',
            'service_provider_self_registration',
            'admin',
            'Sent notification on provider self registration',
            push: 'disable',
            module: 'service',
        );
        $data[] = self::typeRow(
            'Provider Withdraw Request',
            'service_provider_withdraw_request',
            'admin',
            'Sent notification on provider withdraw request',
            push: 'disable',
            module: 'service',
        );

        $data[] = self::typeRow(
            'Provider Registration',
            'service_provider_registration',
            'provider',
            'Sent notification on provider registration',
            push: 'disable',
            module: 'service',
        );
        $data[] = self::typeRow(
            'Provider registration approval',
            'service_provider_registration_approval',
            'provider',
            'Sent notification on provider registration approval',
            push: 'disable',
            module: 'service',
        );
        $data[] = self::typeRow(
            'Provider registration deny',
            'service_provider_registration_deny',
            'provider',
            'Sent notification on provider registration deny',
            push: 'disable',
            module: 'service',
        );
        $data[] = self::typeRow(
            'Provider account block',
            'service_provider_account_block',
            'provider',
            'Sent notification on provider account block',
            module: 'service',
        );
        $data[] = self::typeRow(
            'Provider account unblock',
            'service_provider_account_unblock',
            'provider',
            'Sent notification on provider account unblock',
            module: 'service',
        );
        $data[] = self::typeRow(
            'Provider withdraw approve',
            'service_provider_withdraw_approve',
            'provider',
            'Sent notification on provider withdraw approve',
            module: 'service',
        );
        $data[] = self::typeRow(
            'Provider withdraw rejection',
            'service_provider_withdraw_rejaction',
            'provider',
            'Sent notification on provider withdraw rejection',
            module: 'service',
        );
        $data[] = self::typeRow(
            'Provider booking notification',
            'service_provider_booking_notification',
            'provider',
            'Sent notification on provider booking notification',
            mail: 'disable',
            module: 'service',
        );
        $data[] = self::typeRow(
            'Provider subscription success',
            'service_provider_subscription_success',
            'provider',
            'Sent notification on provider subscription success',
            module: 'service',
        );
        $data[] = self::typeRow(
            'Provider subscription renew',
            'service_provider_subscription_renew',
            'provider',
            'Sent notification on provider subscription renew',
            module: 'service',
        );
        $data[] = self::typeRow(
            'Provider subscription shift',
            'service_provider_subscription_shift',
            'provider',
            'Sent notification on provider subscription shift',
            module: 'service',
        );
        $data[] = self::typeRow(
            'Provider subscription cancel',
            'service_provider_subscription_cancel',
            'provider',
            'Sent notification on provider subscription cancel',
            module: 'service',
        );
        $data[] = self::typeRow(
            'Provider subscription plan update',
            'service_provider_subscription_plan_update',
            'provider',
            'Sent notification on provider subscription plan update',
            push: 'inactive',
            module: 'service',
        );

        $data[] = self::typeRow(
            'Customer booking notification',
            'service_customer_booking_notification',
            'customer',
            'Sent notification on customer booking notification',
            module: 'service',
        );

        return $data;
    }

    public static function getRentalStoreNotificationSetupData($id): array
    {
        $data[] = self::storeRow('Provider account block', 'provider_account_block', $id, 'Sent notification on provider account block', module: 'rental');
        $data[] = self::storeRow('Provider account unblock', 'provider_account_unblock', $id, 'Sent notification on provider account unblock', module: 'rental');
        $data[] = self::storeRow('Provider withdraw approve', 'provider_withdraw_approve', $id, 'Sent notification on provider withdraw approve', module: 'rental');
        $data[] = self::storeRow(
            'Provider withdraw rejection',
            'provider_withdraw_rejaction',
            $id,
            'Sent notification on provider withdraw rejection',
            module: 'rental',
        );
        $data[] = self::storeRow(
            'Provider trip notification',
            'provider_trip_notification',
            $id,
            'Sent notification on provider trip notification',
            mail: 'disable',
            module: 'rental',
        );
        $data[] = self::storeRow(
            'Provider subscription success',
            'provider_subscription_success',
            $id,
            'Sent notification on provider subscription success',
            module: 'rental',
        );
        $data[] = self::storeRow(
            'Provider subscription renew',
            'provider_subscription_renew',
            $id,
            'Sent notification on provider subscription renew',
            module: 'rental',
        );
        $data[] = self::storeRow(
            'Provider subscription shift',
            'provider_subscription_shift',
            $id,
            'Sent notification on provider subscription shift',
            module: 'rental',
        );
        $data[] = self::storeRow(
            'Provider subscription cancel',
            'provider_subscription_cancel',
            $id,
            'Sent notification on provider subscription cancel',
            module: 'rental',
        );
        $data[] = self::storeRow(
            'Provider subscription plan update',
            'provider_subscription_plan_update',
            $id,
            'Sent notification on provider subscription plan update',
            push: 'inactive',
            module: 'rental',
        );


        return $data;
    }

    public static function getServiceStoreNotificationSetupData($id): array
    {
        $data[] = self::storeRow('Provider account block', 'service_provider_account_block', $id, 'Sent notification on provider account block', module: 'service');
        $data[] = self::storeRow(
            'Provider account unblock',
            'service_provider_account_unblock',
            $id,
            'Sent notification on provider account unblock',
            module: 'service',
        );
        $data[] = self::storeRow(
            'Provider withdraw approve',
            'service_provider_withdraw_approve',
            $id,
            'Sent notification on provider withdraw approve',
            module: 'service',
        );
        $data[] = self::storeRow(
            'Provider withdraw rejection',
            'service_provider_withdraw_rejaction',
            $id,
            'Sent notification on provider withdraw rejection',
            module: 'service',
        );
        $data[] = self::storeRow(
            'Provider booking notification',
            'service_provider_booking_notification',
            $id,
            'Sent notification on provider booking notification',
            mail: 'disable',
            module: 'service',
        );
        $data[] = self::storeRow(
            'Provider subscription success',
            'service_provider_subscription_success',
            $id,
            'Sent notification on provider subscription success',
            module: 'service',
        );
        $data[] = self::storeRow(
            'Provider subscription renew',
            'service_provider_subscription_renew',
            $id,
            'Sent notification on provider subscription renew',
            module: 'service',
        );
        $data[] = self::storeRow(
            'Provider subscription shift',
            'service_provider_subscription_shift',
            $id,
            'Sent notification on provider subscription shift',
            module: 'service',
        );
        $data[] = self::storeRow(
            'Provider subscription cancel',
            'service_provider_subscription_cancel',
            $id,
            'Sent notification on provider subscription cancel',
            module: 'service',
        );
        $data[] = self::storeRow(
            'Provider subscription plan update',
            'service_provider_subscription_plan_update',
            $id,
            'Sent notification on provider subscription plan update',
            push: 'inactive',
            module: 'service',
        );


        return $data;
    }
    private static function typeRow(string $title, string $key, string $type, string $subTitle, string $mail = 'active', string $sms = 'disable', string $push = 'active', ?string $module = null): array
    {
        $row = [
            'title' => $title,
            'key' => $key,
            'type' => $type,
            'mail_status' => $mail,
            'sms_status' => $sms,
            'push_notification_status' => $push,
            'sub_title' => $subTitle,
        ];

        if ($module !== null) {
            $row['module_type'] = $module;
        }

        return $row;
    }

    private static function storeRow(string $title, string $key, mixed $storeId, string $subTitle, string $mail = 'active', string $sms = 'disable', string $push = 'active', ?string $module = null): array
    {
        $row = [
            'title' => $title,
            'key' => $key,
            'store_id' => $storeId,
            'mail_status' => $mail,
            'sms_status' => $sms,
            'push_notification_status' => $push,
            'sub_title' => $subTitle,
        ];

        if ($module !== null) {
            $row['module_type'] = $module;
        }

        return $row;
    }
    public static function seedRentalAdminNotificationSettings(): bool
    {
        self::checkAndUpdateAdminNotificationData(self::rentalAdminNotificationRows());

        return true;
    }

    public static function seedServiceAdminNotificationSettings(): bool
    {
        self::checkAndUpdateAdminNotificationData(self::serviceAdminNotificationRows());

        return true;
    }
}
