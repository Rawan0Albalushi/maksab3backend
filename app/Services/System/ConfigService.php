<?php

namespace App\Services\System;

use App\Http\Resources\Common\System\UnitResource;
use App\Support\Promotion\BundleSettings;
use App\Support\Settings\BusinessRules;
use App\Services\Zone\FreeDeliveryService;
use App\Services\Zone\ZoneService;
use App\CentralLogics\Helpers;
use App\Services\BaseService;
use App\Services\System\BusinessSettingService;

class ConfigService extends BaseService
{
    public function getAppConfiguration(array $platform, array $zoneIds = [], mixed $moduleId = null): array
    {
        $settings = $platform['settings'];
        $data = $platform['storage_disks'];
        $openAIStatus = json_decode($settings['openai_config'] ?? '', true);
        $openAIStatus = ($openAIStatus['status'] ?? 0) == 1 ? 1 : 0;

        $DataSetting = $platform['app_download_links'];
        $DataSetting['playstore_url_status'] = (int)($DataSetting['playstore_url_status'] ?? 0);
        $DataSetting['apple_store_url_status'] = (int)($DataSetting['apple_store_url_status'] ?? 0);
        $DataSetting['playstore_url'] = $settings['app_url_android'] ?? null;
        $DataSetting['apple_store_url'] = $settings['app_url_ios'] ?? null;


        $currency_symbol = $platform['currency_symbol'];
        $cod = json_decode($settings['cash_on_delivery'] ?? '', true);
        $digital_payment = json_decode($settings['digital_payment'] ?? '', true);
        $default_location = json_decode($settings['default_location'] ?? '', true);

        // Free delivery moved to a per-(zone, module) setup in S6, and the three global business
        // settings that used to drive this were deprecated with it — nothing reads them any more.
        //
        // The KEYS and their shape are unchanged, because shipped apps branch on them and N9
        // forbids removing or retyping one. What changed is the SOURCE: they now answer for the
        // zone and module this request is scoped to, resolved from the same `zoneId` / `moduleId`
        // headers every other customer endpoint uses.
        //
        // §10.3 — the header may carry several ids for overlapping zones, and the first zone with
        // a setup wins. `activeSetup()` walks them in the order the client sent them.
        $freeDelivery = app(FreeDeliveryService::class)->activeSetup($zoneIds, $moduleId);

        // Which payment methods this zone permits. The keys below keep their names, their types
        // and their position (N9) -- only the SOURCE narrows, from the third-party settings alone
        // to those settings AND the zone's own columns, which is what order placement now
        // enforces. An app told COD is available and then refused at checkout is the worse of the
        // two failures. With no `zoneId` header nothing can be narrowed and the globals answer,
        // so a client that has not picked a location yet still sees what the platform supports.
        $zonePayments = app(ZoneService::class)->allowedPaymentMethodsForZoneIds($zoneIds);

        $admin_free_delivery = [
            'status' => (bool) $freeDelivery,
            // apiType(), not the column: the setup renamed the types and the apps switch on the
            // old names (§10.2).
            'type' => $freeDelivery?->apiType(),
            'free_delivery_over' => (float) ($freeDelivery?->minimum_order_amount ?? 0),
            // §10.2 — kept present so nothing branches on its absence. The zone model has no
            // distance criterion, so it is always zero.
            'free_delivery_distance' => 0.0,
        ];

        $additional_charge = (float)($settings['additional_charge'] ?? 0);
        $module = $platform['module'];
        $languages = app(BusinessSettingService::class)->value('language');
        $lang_array = [];
        foreach ($languages as $language) {
            array_push($lang_array, [
                'key' => $language,
                'value' => Helpers::get_language_name($language),
            ]);
        }
        $system_languages = app(BusinessSettingService::class)->value('system_language');
        $sys_lang_array = [];
        foreach ($system_languages as $language) {
            array_push($sys_lang_array, [
                'key' => $language['code'],
                'value' => Helpers::get_language_name($language['code']),
                'direction' => $language['direction'],
                'default' => $language['default'],
            ]);
        }
        $social_login = [];
        foreach (app(BusinessSettingService::class)->value('social_login') as $social) {
            $config = [
                'login_medium' => $social['login_medium'],
                'status' => (bool)$social['status'],
            ];
            array_push($social_login, $config);
        }
        $apple_login = [];
        $apples = app(BusinessSettingService::class)->value('apple_login');
        if (is_array($apples)) {
            foreach ($apples as $apple) {
                $config = [
                    'login_medium' => $apple['login_medium'],
                    'status' => (bool)$apple['status'],
                    'client_id' => $apple['client_id'],
                    'client_id_app' => $apple['client_id_app'] ?? '',
                    'redirect_url_flutter' => $apple['redirect_url_flutter'] ?? '',
                    'redirect_url_react' => $apple['redirect_url_react'] ?? '',
                ];
                array_push($apple_login, $config);
            }
        }

        $published_status = 0;
        $payment_published_status = config('get_payment_publish_status');
        if (isset($payment_published_status[0]['is_published'])) {
            $published_status = $payment_published_status[0]['is_published'];
        }

        $active_addon_payment_lists = Helpers::getActivePaymentGateways();

        $digital_payment_infos = [
            // Mirrors the top-level flag, zone narrowing included -- the apps read whichever of
            // the two they were written against, and the pair disagreeing is its own defect.
            'digital_payment' => $zonePayments['digital_payment'],
            'plugin_payment_gateways' => (bool)($published_status ? true : false),
            'default_payment_gateways' => (bool)($published_status ? false : true),
        ];
            $dm_loyality_point_data = [
            'loyality_point_status' => (bool) (($settings['dm_loyality_point_status'] ?? null) == 1 ? true : false),
            'loyality_point_per_order' => (float) ($settings['dm_loyality_point_per_order'] ?? null) ?? 0,
            'loyality_point_conversion_rate' => (float) ($settings['dm_loyality_point_conversion_rate'] ?? null) ?? 0,
            'min_loyality_point_to_convert' => (float) ($settings['dm_min_loyality_point_to_convert'] ?? null) ?? 0,
        ];

        $dm_referral_data = [
            'referal_status' => (bool) (($settings['dm_referal_status'] ?? null) == 1 ? true : false),
            'referal_amount' => (float) ($settings['dm_referal_amount'] ?? null) ?? 0,
            'referal_bonus' => (float) ($settings['dm_referal_bonus'] ?? null) ?? 0,
        ];

        if (($settings['subscription_free_trial_type'] ?? null) == 'year') {
            $trial_period = ($settings['subscription_free_trial_days'] ?? null) > 0 ? ($settings['subscription_free_trial_days'] ?? null) / 365 : 0;
        } elseif (($settings['subscription_free_trial_type'] ?? null) == 'month') {
            $trial_period = ($settings['subscription_free_trial_days'] ?? null) > 0 ? ($settings['subscription_free_trial_days'] ?? null) / 30 : 0;
        } else {
            $trial_period = ($settings['subscription_free_trial_days'] ?? null) > 0 ? ($settings['subscription_free_trial_days'] ?? null) : 0;
        }

        $vehicle_distance_min = $platform['vehicle_minimums']['distance'];
        $vehicle_hourly_min = $platform['vehicle_minimums']['hourly'];
        $vehicle_day_wise_min = $platform['vehicle_minimums']['day_wise'];
        $systemTax = $platform['system_tax'];
        $maintenance_mode_data = $platform['maintenance_mode'];
        $data = [
            'business_name' => $settings['business_name'] ?? null,
            'logo_full_url' => Helpers::get_full_url('business', $settings['logo'] ?? null, $data['logo_storage'] ?? 'public'),
            'address' => $settings['address'] ?? null,
            'phone' => $settings['phone'] ?? null,
            'email' => $settings['email_address'] ?? null,

            'country' => $settings['country'] ?? null,
            'default_location' => ['lat' => $default_location ? $default_location['lat'] : '23.757989', 'lng' => $default_location ? $default_location['lng'] : '90.360587'],
            'currency_symbol' => $currency_symbol,
            'currency_symbol_direction' => $settings['currency_symbol_position'] ?? null,

            // Additive only (N9). `distance_unit` is the enum a client matches on —
            // payload keys suffix it exactly, so `distance_` . distance_unit resolves against
            // the distance_km / distance_mi pair store payloads carry (M5). The label is
            // translated here so apps carry no km/mi mapping of their own.
            //
            // NOTE FOR API CONSUMERS: this setting does NOT change the unit of the `distance`
            // a client POSTs to place_order. That field is kilometres, always — decision D2,
            // §3.6. It governs display, and which unit stored rates are read in.
            'distance_unit' => app(DistanceService::class)->unit(),
            'distance_unit_label' => app(DistanceService::class)->unitLabel(),

            // The same question answered once for all three settings (parcel brief decision 4).
            // The two flat keys above stay for shipped apps (N9) and report the same value as the
            // `distance` entry here; anything new reads this block, because weight and dimension
            // have no flat keys and a client was otherwise left hard-coding "KG" beside a band.
            //
            // A switch does NOT convert stored numbers — a band saved as `0 - 2` under kilograms
            // still reads `0 - 2` under pounds (§0b). Suffix what `label` says; never convert.
            'units' => UnitResource::renderCollection(
                array_values(app(MeasurementUnitService::class)->descriptors())
            ),

            'app_minimum_version_android' => (float)($settings['app_minimum_version_android'] ?? 0),
            'app_url_android' => $settings['app_url_android'] ?? null,
            'app_url_ios' => $settings['app_url_ios'] ?? null,

            'app_minimum_version_android_store' => (float)($settings['app_minimum_version_android_store'] ?? 0),
            'app_url_android_store' => ($settings['app_url_android_store'] ?? null),
            'app_minimum_version_ios_store' => (float)($settings['app_minimum_version_ios_store'] ?? 0),
            'app_url_ios_store' => ($settings['app_url_ios_store'] ?? null),

            'app_minimum_version_android_deliveryman' => (float)($settings['app_minimum_version_android_deliveryman'] ?? 0),
            'app_url_android_deliveryman' => ($settings['app_url_android_deliveryman'] ?? null),
            'app_minimum_version_ios_deliveryman' => (float)($settings['app_minimum_version_ios_deliveryman'] ?? 0),
            'app_url_ios_deliveryman' => ($settings['app_url_ios_deliveryman'] ?? null),

            'app_minimum_version_android_serviceman' => (float)($settings['app_minimum_version_android_serviceman'] ?? 0),
            'app_url_android_serviceman' => ($settings['app_url_android_serviceman'] ?? null),
            'app_minimum_version_ios_serviceman' => (float)($settings['app_minimum_version_ios_serviceman'] ?? 0),
            'app_url_ios_serviceman' => ($settings['app_url_ios_serviceman'] ?? null),
            'app_minimum_version_ios' => (float)($settings['app_minimum_version_ios'] ?? 0),




            'prescription_order_status' => (bool)($settings['prescription_order_status'] ?? false),
            'schedule_order' => (bool)($settings['schedule_order'] ?? false),
            'order_delivery_verification' => (bool)($settings['order_delivery_verification'] ?? false),
            'verified_store_status' => (bool)($settings['verified_seller_badge'] ?? false),

            // Product Bundle — Business Settings > Order > Product Bundle. Read through
            // BundleSettings rather than off `$settings` directly: the master switch and the
            // module list are two rows, the second is JSON, and the type list has three module
            // types excluded from it. That rule already lives in one place and the vendor API,
            // the cart and the admin screens all read it there.
            //
            // Three keys rather than one nested block, matching the flat feature flags around it.
            // `module_types` is the raw setting: which module types may sell bundles, and [] when
            // the feature is off, so a client never has to check the switch and the list
            // separately. `available` is the same question answered for the module THIS request
            // was scoped to — the payload is already cached per `module_id`, so it is safe to
            // vary — because a client holds a module id, not a module type, and would otherwise
            // have to map one to the other itself.
            'product_bundle_status' => BundleSettings::enabled(),
            'product_bundle_module_types' => BundleSettings::enabledModuleTypes(),
            'product_bundle_available' => BundleSettings::allowsModule($moduleId),
            'cash_on_delivery' => $zonePayments['cash_on_delivery'],
            'digital_payment' => $zonePayments['digital_payment'],
            'digital_payment_info' => $digital_payment_infos,
            'demo' => (bool)(getEnvMode() == 'demo' ? true : false),
            'maintenance_mode' => (bool)app(BusinessSettingService::class)->value('maintenance_mode') ?? 0,
            'order_confirmation_model' => BusinessRules::orderConfirmationModel(),
            'show_dm_earning' => (bool)($settings['show_dm_earning'] ?? false),
            'canceled_by_deliveryman' => (bool)($settings['canceled_by_deliveryman'] ?? false),
            'canceled_by_store' => (bool)($settings['canceled_by_store'] ?? false),
            'timeformat' => (string)($settings['timeformat'] ?? ''),
            'sys_language' => $sys_lang_array,
            'language' => $lang_array,
            'social_login' => $social_login,
            'apple_login' => $apple_login,
            'toggle_veg_non_veg' => (bool)($settings['toggle_veg_non_veg'] ?? false),
            'toggle_dm_registration' => (bool)($settings['toggle_dm_registration'] ?? false),
            'toggle_store_registration' => (bool)($settings['toggle_store_registration'] ?? false),
            'refund_active_status' => (bool)($settings['refund_active_status'] ?? false),
            'schedule_order_slot_duration' => (int)($settings['schedule_order_slot_duration'] ?? 0),
            'digit_after_decimal_point' => (int)config('round_up_to_digit'),
            'module_config' => config('module'),
            'module' => $module,
            // A14 — these two settings no longer price anything: a parcel costs what the zone's
            // delivery rule quotes plus the category's own additional charge. The keys stay
            // because shipped apps read them (N9) and answer 0.00, since reporting the stored
            // rate would put a fee on a customer's screen that nothing charges.
            'parcel_per_km_shipping_charge' => 0.0,
            'parcel_minimum_shipping_charge' => 0.0,
            'social_media' => $platform['social_media'],
            'footer_text' => $settings['footer_text'] ?? '',
            'cookies_text' => $settings['cookies_text'] ?? '',
            'fav_icon' => $settings['icon'] ?? null,
            'fav_icon_full_url' => Helpers::get_full_url('business', $settings['icon'] ?? null, $data['icon_storage'] ?? 'public'),
            'dm_tips_status' => (int)($settings['dm_tips_status'] ?? 0),
            'loyalty_point_exchange_rate' => (int)(isset($settings['loyalty_point_item_purchase_point']) ? $settings['loyalty_point_exchange_rate'] : 0),
            'loyalty_point_item_purchase_point' => (float)($settings['loyalty_point_item_purchase_point'] ?? 0.0),
            'loyalty_point_status' => (int)($settings['loyalty_point_status'] ?? 0),
            'customer_wallet_status' => (int)($settings['wallet_status'] ?? 0),
            'ref_earning_status' => (int)($settings['ref_earning_status'] ?? 0),
            'ref_earning_exchange_rate' => (float)($settings['ref_earning_exchange_rate'] ?? 0),
            'refund_policy' => (int)($platform['policy_statuses']['refund_policy_status']),
            'cancelation_policy' => (int)($platform['policy_statuses']['cancellation_policy_status']),
            'shipping_policy' => (int)($platform['policy_statuses']['shipping_policy_status']),
            'loyalty_point_minimum_point' => (int)($settings['loyalty_point_minimum_point'] ?? 0),

            'home_delivery_status' => (int)($settings['home_delivery_status'] ?? 0),
            'takeaway_status' => (int)($settings['takeaway_status'] ?? 0),
            'active_payment_method_list' => $active_addon_payment_lists,
            'additional_charge_status' => (int)($settings['additional_charge_status'] ?? 0),
            'additional_charge_name' => ($settings['additional_charge_name'] ?? 'Service Charge'),
            'additional_charge' => $additional_charge,
            'partial_payment_status' => (int)($settings['partial_payment_status'] ?? 0),
            'partial_payment_method' => ($settings['partial_payment_method'] ?? ''),
            'add_fund_status' => (int)($settings['add_fund_status'] ?? 0),
            'dm_picture_upload_status' => (int)($settings['dm_picture_upload_status'] ?? 0),
            'offline_payment_status' => (int) $zonePayments['offline_payment'],
            'websocket_status' => (int)($settings['websocket_status'] ?? 0),
            'websocket_url' => ($settings['websocket_url'] ?? ''),
            'websocket_port' => (int)($settings['websocket_port'] ?? 6001),
            'websocket_key' => env('PUSHER_APP_KEY'),
            'websocket_scheme' => env('PUSHER_SCHEME'),
            'guest_checkout_status' => (int)($settings['guest_checkout_status'] ?? 0),
            'disbursement_type' => (string)($settings['disbursement_type'] ?? 'manual'),
            'restaurant_disbursement_waiting_time' => (int)($settings['restaurant_disbursement_waiting_time'] ?? 0),
            'dm_disbursement_waiting_time' => (int)($settings['dm_disbursement_waiting_time'] ?? 0),
            'min_amount_to_pay_store' => (float)($settings['min_amount_to_pay_store'] ?? 0),
            'min_amount_to_pay_dm' => (float)($settings['min_amount_to_pay_dm'] ?? 0),
            'new_customer_discount_status' => (int)($settings['new_customer_discount_status'] ?? 0),
            'new_customer_discount_amount' => (float)($settings['new_customer_discount_amount'] ?? 0),
            'new_customer_discount_amount_type' => ($settings['new_customer_discount_amount_type'] ?? 'amount'),
            'new_customer_discount_amount_validity' => (int)($settings['new_customer_discount_amount_validity'] ?? 0),
            'new_customer_discount_validity_type' => ($settings['new_customer_discount_validity_type'] ?? 'day'),
            'store_review_reply' => (int)($settings['store_review_reply'] ?? 0),
            'admin_commission' => (float)($settings['admin_commission'] ?? 0),
            'subscription_deadline_warning_days' => (int)($settings['subscription_deadline_warning_days'] ?? 1),
            'subscription_deadline_warning_message' => $settings['subscription_deadline_warning_message'] ?? null,
            'subscription_business_model' => (int)($settings['subscription_business_model'] ?? 1),
            'commission_business_model' => (int)($settings['commission_business_model'] ?? 1),
            'subscription_free_trial_days' => (int)$trial_period,
            'subscription_free_trial_type' => ($settings['subscription_free_trial_type'] ?? 'day'),
            'subscription_free_trial_status' => (int)($settings['subscription_free_trial_status'] ?? 0),
            'country_picker_status' => (int)($settings['country_picker_status'] ?? 1),
            'firebase_otp_verification' => (int)($settings['firebase_otp_verification'] ?? 0),
            'centralize_login' => [
                'manual_login_status' => (int)($settings['manual_login_status'] ?? 0),
                'otp_login_status' => (int)($settings['otp_login_status'] ?? 0),
                'social_login_status' => (int)($settings['social_login_status'] ?? 0),
                'google_login_status' => (int)($settings['google_login_status'] ?? 0),
                'facebook_login_status' => (int)($settings['facebook_login_status'] ?? 0),
                'apple_login_status' => (int)($settings['apple_login_status'] ?? 0),
                'email_verification_status' => (int)($settings['email_verification_status'] ?? 0),
                'phone_verification_status' => (int)($settings['phone_verification_status'] ?? 0),
                'send_otp_via' => (string)($settings['send_otp_via'] ?? null),
            ],

            'vehicle_distance_min' => (float)$vehicle_distance_min ?? 0,
            'vehicle_hourly_min' => (float)$vehicle_hourly_min ?? 0,
            'vehicle_day_wise_min' => (float)$vehicle_day_wise_min ?? 0,
            'admin_free_delivery' => $admin_free_delivery,
            'is_sms_active' => $platform['has_active_sms'],
            'is_mail_active' => (bool)config('mail.status'),
            'system_tax_type' => $systemTax?->tax_type ?? null,
            'system_tax_include_status' => (int)$systemTax?->is_included,

            'parcel_cancellation_status' => (int)(1),
            'parcel_return_time_fee' => json_decode($settings['parcel_return_time_fee'] ?? ''),

            'open_ai_status' => (int)$openAIStatus,

            'dm_loyality_point_data' => $dm_loyality_point_data,

            'parcel_cancellation_basic_setup' => json_decode($settings['parcel_cancellation_basic_setup'] ?? ''),


            'dm_referral_data' => $dm_referral_data,
            'seo_page_list' => Helpers::seoPageList(),
            'download_user_app_links' => $DataSetting,
            'validation_config' => [
                'image_format' => IMAGE_FORMAT,
                'image_extension' => IMAGE_EXTENSION,
                'image_format_for_validation' => IMAGE_FORMAT_FOR_VALIDATION,
                'video_format' => VIDEO_FORMAT,
                'video_extension' => VIDEO_EXTENSION,
                'product_video_max_file_size' => PRODUCT_VIDEO_MAX_FILE_SIZE,
                'document_format' => DOCUMENT_FORMAT,
                'document_extension' => DOCUMENT_EXTENSION,
                'audio_format' => AUDIO_FORMAT,
                'audio_extension' => AUDIO_EXTENSION,
                'file_format' => FILE_FORMAT,
                'file_format_for_image_picker' => FILE_FORMAT_FOR_IMAGE_PICKER,
                'file_extension' => FILE_EXTENSION,
                'max_file_size' => MAX_FILE_SIZE,
            ],
            'repeat_order_option' => (int)($settings['repeat_order_option'] ?? false),
            'monthly_order_reminder' => (int)($settings['monthly_order_reminder'] ?? false),
            'monthly_order_reminder_days_before' => (int)($settings['monthly_order_reminder_days_before'] ?? 3),
            'monthly_order_reminder_before_unit' => $settings['monthly_order_reminder_before_unit'] ?? 'day',
            'maintenance_mode_data' => count($maintenance_mode_data) > 0 ? $maintenance_mode_data : null,
            'store_category_status' => Helpers::storeCategoryStatus(),
            'pro_member_status' => (int)($settings['pro_member_status'] ?? 0),
            'customer_personalization_status' => (int)($settings['customer_personalization_status'] ?? 0),
            'ai_chat_status' => (int)(($openAIStatus == 1 && ($settings['ai_chat_status'] ?? 0)) ? 1 : 0),

        ];

        if(addon_published_status('RideShare')){
            $rideConfigs = $platform['ride_share']['configs'];

            $rider_referral_data = [
                'referal_status' => (bool) (data_get($rideConfigs,'rider_referal_status') == 1 ? true : false),
                'referal_amount' => (float) data_get($rideConfigs, 'rider_referal_amount') ?? 0,
                'referal_bonus' => (float) data_get($rideConfigs, 'rider_referal_bonus') ?? 0,
            ];
            $rider_loyality_point_data = [
                'loyality_point_status' => (bool) (data_get($rideConfigs, 'rider_loyality_point_status') == 1 ? true : false),
                'loyality_point_conversion_rate' => (float) data_get($rideConfigs, 'rider_loyality_point_conversion_rate') ?? 0,
                'min_loyality_point_to_convert' => (float) data_get($rideConfigs, 'rider_min_loyality_point_to_convert') ?? 0,
            ];
            $rideData = [
                'vehicle_fuel_types' => $this->vehicleFuelTypes(),
                'vehicle_transmission_types' => $this->vehicleTransmissionTypes(),
                'ride_vat' => $platform['ride_share']['vat']['totalTaxPercent'] ?? 0,

                'rider_can_review_customer' => (int)($rideConfigs['rider_can_review_customer'] ?? 0),
                'safety_feature_status' => (int)($rideConfigs['safety_feature_status'] ?? 0),
                'ride_safety_delay_time' => isset($rideConfigs['ride_safety_delay_time']) ? (int)($rideConfigs['ride_safety_delay_time']) : null,
                'ride_safety_delay_time_format' => $rideConfigs['ride_safety_delay_time_format'] ?? 'minute',
                'safety_feature_after_ride_complete_status' => isset($rideConfigs['safety_feature_after_ride_complete_status']) ? (int)($rideConfigs['safety_feature_after_ride_complete_status'] ?? 0) : '',
                'safety_feature_after_ride_complete_time' => isset($rideConfigs['safety_feature_after_ride_complete_time']) ? (int)($rideConfigs['safety_feature_after_ride_complete_time'] ?? 0) : '',
                'safety_feature_after_ride_complete_time_format' => $rideConfigs['safety_feature_after_ride_complete_time_format'] ?? 'minute',
                'emergency_govt_number' => is_string($rideConfigs['emergency_govt_number'] ?? null) ? $rideConfigs['emergency_govt_number'] : '',
                'ride_search_radius' => (int)($rideConfigs['search_radius'] ?? 0),
                'rider_completion_radius' => (int)($rideConfigs['rider_completion_radius'] ?? 0),
                'ride_commission' => (float)($rideConfigs['ride_commission'] ?? 0),
                'bid_on_fare' => (int)($rideConfigs['bid_on_fare'] ?? 0),
                'ride_otp_confirmation' => (int)($rideConfigs['ride_otp_confirmation'] ?? 0),
                'ride_request_active_time' => (int)($rideConfigs['ride_request_active_time'] ?? 0),
                'toggle_rider_registration' => ($rideConfigs['toggle_rider_registration'] ?? 0) == 1,
                'show_rider_earning' => ($rideConfigs['show_rider_earning'] ?? 0) == 1,
                'rider_level_status' => (int)($rideConfigs['rider_level_status'] ?? 0),
                'rider_tips_status' => (int)($rideConfigs['rider_tips_status'] ?? 0),
                'cash_in_hand_overflow_rider' => (int)($rideConfigs['cash_in_hand_overflow_rider'] ?? 0),
                'rider_max_cash_in_hand' => (float)($rideConfigs['rider_max_cash_in_hand'] ?? 0),
                'min_amount_to_pay_rider' => (float)($rideConfigs['min_amount_to_pay_rider'] ?? 0),
                'rider_loyality_point_data' => $rider_loyality_point_data,
                'rider_referral_data' => $rider_referral_data,
                'customer_route_preference' => (int)($rideConfigs['customer_route_preference'] ?? 0),
                'rider_faqs' => $platform['ride_share']['rider_faqs'],
                'app_minimum_version_android_rider' => (float)($settings['app_minimum_version_android_rider'] ?? 0),
                'app_url_android_rider' => ($settings['app_url_android_rider'] ?? null),
                'app_minimum_version_ios_rider' => (float)($settings['app_minimum_version_ios_rider'] ?? 0),
                'app_url_ios_rider' => ($settings['app_url_ios_rider'] ?? null),

            ];

            $rideShareRows = $platform['ride_share']['page_rows'];

            $buildHeroBlock = function (string $prefix) use ($rideShareRows) {
                $heroIntroImage = $rideShareRows->get($prefix.'hero_intro_image');
                $points = [];
                for ($i = 1; $i <= 3; $i++) {
                    $pointImage = $rideShareRows->get($prefix."hero_point_image_card_$i");
                    $points[] = [
                        'status' => (int) ($rideShareRows->get($prefix."hero_point_status_card_$i")?->value ?? 0),
                        'title' => $rideShareRows->get($prefix."hero_point_title_card_$i")?->value,
                        'image_full_url' => Helpers::get_full_url(
                            'ride_share_hero_section',
                            $pointImage?->value,
                            $pointImage?->storage[0]?->value ?? 'public',
                            'aspect_1'
                        ),
                    ];
                }
                return [
                    'status' => (int) ($rideShareRows->get($prefix.'hero_section_status')?->value ?? 0),
                    'intro' => [
                        'title' => $rideShareRows->get($prefix.'hero_intro_title')?->value,
                        'sub_title' => $rideShareRows->get($prefix.'hero_intro_sub_title')?->value,
                        'image_full_url' => Helpers::get_full_url(
                            'ride_share_hero_section',
                            $heroIntroImage?->value,
                            $heroIntroImage?->storage[0]?->value ?? 'public',
                            'aspect_1'
                        ),
                    ],
                    'points' => $points,
                ];
            };

            $topCustomerIds = $platform['ride_share']['top_customer_ride_counts'];

            $topCustomers = $platform['ride_share']['top_customers']
                ->map(fn ($user) => [
                    'name' => trim($user->f_name . ' ' . $user->l_name),
                    'image_full_url' => $user->image_full_url,
                    'total_rides' => (int) ($topCustomerIds[$user->id] ?? 0),
                ])
                ->sortByDesc('total_rides')
                ->values();

            $rideData['react_ride_share_page'] = [
                'customer' => [
                    'hero_section' => $buildHeroBlock(''),
                ],
                'rider' => [
                    'hero_section' => $buildHeroBlock('rider_'),
                ],
                'top_customers' => $topCustomers,
                'total_customers' => $platform['ride_share']['total_customers'],
            ];

            $data = array_merge($data, $rideData);
        }

        if (addon_published_status('ReelsModule')) {
            $data['reels_module'] = [
                'vendor_can_upload_reels' => (int) (app(BusinessSettingService::class)->value('vendor_can_upload_reels') ?? 0),
                'reels_max_upload_size_mb' => (int) (app(BusinessSettingService::class)->value('reels_max_upload_size_mb') ?? 15),
                'reels_max_duration' => (int) (app(BusinessSettingService::class)->value('reels_max_duration') ?? 30),
                'reels_max_duration_unit' => (string) (app(BusinessSettingService::class)->value('reels_max_duration_unit') ?? 'min'),
                'reels_upload_limit_unlimited' => (int) (app(BusinessSettingService::class)->value('reels_upload_limit_unlimited') ?? 1),
                'reels_upload_limit' => (int) (app(BusinessSettingService::class)->value('reels_upload_limit') ?? 0),
                'reels_upload_limit_type' => (string) (app(BusinessSettingService::class)->value('reels_upload_limit_type') ?? 'week'),
            ];
        }

        if (addon_published_status('Service')) {
            $serviceSettings = $platform['service_module']['settings'];
            $biddingSystem = (bool) ($serviceSettings['service_bidding_system'] ?? 0);
            $scheduleBooking = (bool) ($serviceSettings['service_schedule_booking'] ?? 0);
            $timeRestrictionStatus = (bool) ($serviceSettings['service_schedule_time_restriction_status'] ?? 0);

            $canCancelBooking = (bool) ($serviceSettings['service_provider_can_cancel_booking'] ?? 0);
            $serviceGallery = (bool) ($serviceSettings['service_gallery'] ?? 0);
            $serviceApproval = (bool) ($serviceSettings['service_approval'] ?? 0);
            $approvalRaw = $serviceSettings['service_approval_datas'] ?? null;
            $approvalDatas = is_array($approvalRaw) ? $approvalRaw : (json_decode((string) $approvalRaw, true) ?: []);

            $serviceTaxSetup = $platform['service_module']['tax_setup'];
            $serviceTaxPercentage = $platform['service_module']['tax_percentage'];

            $data['service_module'] = [
                'instant_booking' => (bool) ($serviceSettings['service_instant_booking'] ?? 0),
                'repeat_booking' => (bool) ($serviceSettings['service_repeat_booking'] ?? 0),
                'rebooking_option' => (bool) ($serviceSettings['service_rebooking_option'] ?? 0),
                'schedule_booking' => $scheduleBooking,
                'schedule_time_restriction_status' => $scheduleBooking && $timeRestrictionStatus,
                'schedule_time_restriction_value' => ($scheduleBooking && $timeRestrictionStatus)
                    ? (int) ($serviceSettings['service_schedule_time_restriction_value'] ?? 0)
                    : null,
                'schedule_time_restriction_unit' => (string) ($serviceSettings['service_schedule_time_restriction_unit'] ?? 'hours'),
                'bidding_system' => $biddingSystem,
                'see_other_providers_offers' => $biddingSystem && (bool) ($serviceSettings['service_see_other_providers_offers'] ?? 0),
                'post_validation_days' => (int) ($serviceSettings['service_post_validation_days'] ?? 0),
                'otp_for_complete_service' => (bool) ($serviceSettings['service_otp_for_complete_service'] ?? 0),
                'complete_photo_evidence' => (bool) ($serviceSettings['service_complete_photo_evidence'] ?? 0),
                'provider_can_cancel_booking' => $canCancelBooking,
                'provider_can_edit_booking' => (bool) ($serviceSettings['service_provider_can_edit_booking'] ?? 0),
                'provider_can_reply_review' => (bool) ($serviceSettings['service_provider_can_reply_review'] ?? 0),
                'provider_category_status' => (bool) ($serviceSettings['service_provider_category_status'] ?? 0),
                'provider_self_registration' => (bool) ($settings['toggle_store_registration'] ?? 0),
                'review_section' => (bool) ($serviceSettings['service_review_section'] ?? 0),
                'provider_verified_badge' => (bool) ($serviceSettings['service_provider_verified_badge'] ?? 0),
                'at_provider_place' => (bool) ($serviceSettings['service_at_provider_place'] ?? 0),
                'service_gallery' => $serviceGallery,
                'access_all_services' => $serviceGallery && (bool) ($serviceSettings['service_access_all_services'] ?? 0),
                'serviceman_cancel_booking_req' => $canCancelBooking && (bool) ($serviceSettings['service_serviceman_cancel_booking_req'] ?? 0),
                'approval' => $serviceApproval,
                'approval_criteria' => $serviceApproval ? [
                    'add_new_service' => (bool) data_get($approvalDatas, 'Add_new_service', 0),
                    'update_service_price' => (bool) data_get($approvalDatas, 'Update_service_price', 0),
                    'update_service_variation' => (bool) data_get($approvalDatas, 'Update_service_variation', 0),
                    'update_anything_in_service_details' => (bool) data_get($approvalDatas, 'Update_anything_in_service_details', 0),
                ] : null,
                'tax' => [
                    'tax_type' => $serviceTaxSetup?->tax_type ?? null,
                    'tax_status' => $serviceTaxSetup && ! $serviceTaxSetup->is_included ? 'excluded' : 'included',
                    'tax_include_status' => (int) ($serviceTaxSetup?->is_included ?? 0),
                    'tax_percentage' => (float) ($serviceTaxPercentage['totalTaxPercent'] ?? 0),
                ],
            ];
        }

        return $data;
    }


    private function vehicleFuelTypes(): mixed
    {
        return ['octan', 'diesel', 'cng', 'petrol'];
    }

    private function vehicleTransmissionTypes(): mixed
    {
        return ['automatic', 'manual', 'continuously_variable', 'dual_clutch', 'semi_automatic'];
    }
}
