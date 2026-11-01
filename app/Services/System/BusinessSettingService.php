<?php

namespace App\Services\System;

use App\Models\BusinessSetting;
use App\Services\BaseService;
use App\Support\Cache\ApiCache;

class BusinessSettingService extends BaseService
{
    private const APP_CONFIG_KEYS = [
        'currency_code','cash_on_delivery','digital_payment','default_location','business_name','logo','address','phone','email_address','country','currency_symbol_position','app_minimum_version_android',
        'app_url_android','app_minimum_version_ios','app_url_ios','app_url_android_store','app_minimum_version_ios_store','app_url_ios_store','app_minimum_version_ios_deliveryman','app_url_ios_deliveryman',
        'app_minimum_version_android_deliveryman','app_minimum_version_android_store','app_url_android_deliveryman','app_minimum_version_android_rider','app_url_android_rider','app_minimum_version_ios_rider',
        'app_url_ios_rider','app_minimum_version_android_serviceman','app_url_android_serviceman','app_minimum_version_ios_serviceman','app_url_ios_serviceman','schedule_order','order_delivery_verification','show_dm_earning','canceled_by_deliveryman','canceled_by_store','timeformat','toggle_veg_non_veg','toggle_dm_registration',
        'toggle_store_registration','schedule_order_slot_duration','footer_text','loyalty_point_exchange_rate','loyalty_point_item_purchase_point',
        'loyalty_point_status','loyalty_point_minimum_point','wallet_status','dm_tips_status','ref_earning_status','ref_earning_exchange_rate','refund_active_status','refund','cancelation',
        'shipping_policy','prescription_order_status','icon','cookies_text','home_delivery_status','takeaway_status','additional_charge','additional_charge_status','additional_charge_name',
        'dm_picture_upload_status','partial_payment_status','partial_payment_method','add_fund_status','offline_payment_status','websocket_url','websocket_port','websocket_status','guest_checkout_status','disbursement_type','restaurant_disbursement_waiting_time','dm_disbursement_waiting_time','min_amount_to_pay_store','min_amount_to_pay_dm','admin_commission','new_customer_discount_status','new_customer_discount_amount','new_customer_discount_amount_type','new_customer_discount_amount_validity','new_customer_discount_validity_type','store_review_reply','subscription_business_model','commission_business_model','subscription_deadline_warning_days','subscription_deadline_warning_message','subscription_free_trial_days','subscription_free_trial_type','subscription_free_trial_status','country_picker_status','firebase_otp_verification','manual_login_status','otp_login_status','social_login_status','google_login_status','facebook_login_status','apple_login_status','email_verification_status','phone_verification_status','send_otp_via','admin_free_delivery_option','admin_free_delivery_status','free_delivery_over',
        'parcel_cancellation_status','parcel_cancellation_basic_setup','parcel_return_time_fee','openai_config','dm_loyality_point_status','dm_loyality_point_per_order',
        'dm_loyality_point_conversion_rate','dm_min_loyality_point_to_convert','dm_referal_status','dm_referal_amount','dm_referal_bonus','pro_member_status',
        'repeat_order_option','monthly_order_reminder','monthly_order_reminder_days_before','monthly_order_reminder_before_unit',
        'customer_personalization_status','ai_chat_status','verified_seller_badge',

    ];
    private static $allSettings = null;

    private static array $memo = [];

    private static array $modelMemo = [];

    public function value(string $key, bool $decode = true, array $relations = []): mixed
    {
        try {
            $memoKey = empty($relations) ? $key : $key.'::'.implode(',', $relations);

            if (array_key_exists($memoKey, self::$memo)) {
                $data = self::$memo[$memoKey];
            } else {
                if (is_null(self::$allSettings)) {
                    self::$allSettings = ApiCache::remember('business_settings', 'all_data', function () {
                        return BusinessSetting::select('key', 'value')->get();
                    });
                }

                $data = self::$allSettings->firstWhere('key', $key);
                if ($data && ! empty($relations)) {
                    $data->loadMissing($relations);
                }
                self::$memo[$memoKey] = $data;
            }

            if (! isset($data['value'])) {
                return null;
            }

            $value = $data['value'];
            if ($decode && is_string($value)) {
                $decoded = json_decode($value, true);

                return is_null($decoded) ? $value : $decoded;
            }

            return $value;
        } catch (\Throwable $throwable) {
            return null;
        }
    }

    public function valuesFor(array $keys, bool $decode = false): array
    {
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = $this->value($key, $decode);
        }

        return $data;
    }

    public static function forgetCache(): void
    {
        self::$allSettings = null;
        self::$memo = [];
    }

    public function findValue(string $key): mixed
    {
        return $this->byKeysQuery([$key])->value('value');
    }
    public function getAppConfigSettings(): array
    {
        return ApiCache::remember('business_settings', 'config_keys', function () {
            return array_column($this->byKeysQuery(self::APP_CONFIG_KEYS)->get()->toArray(), 'value', 'key');
        });
    }
    public function findStorageDisk(string $key): string
    {
        return ApiCache::remember('business_settings', "config_{$key}_storage", function () use ($key) {
            return BusinessSetting::withStorage()->where('key', $key)->first()?->storage[0]?->value ?? 'public';
        });
    }
    public function findByKey(string $key): mixed
    {
        return $this->byKeysQuery([$key])->first();
    }
    public function getValuesByKeys(array $keys): array
    {
        return $this->byKeysQuery($keys)->pluck('value', 'key')->all();
    }
    public function findDecodedValue(string $key): mixed
    {
        $row = $this->findByKey($key);

        return $row ? json_decode($row->value, true) : null;
    }

    public function digitalPaymentEnabled(): bool
    {
        return ($this->value('digital_payment')['status'] ?? 0) != 0;
    }

    public function saveValue(string $key, mixed $value): void
    {
        $setting = BusinessSetting::firstOrNew(['key' => $key]);
        $setting->value = $value;
        $setting->save();
    }

    public function insertKeyIfMissing(string $key, mixed $value = null): bool
    {
        if (! $this->byKeysQuery([$key])->exists()) {
            $this->saveValue($key, $value);
        }

        return true;
    }

    public function findModelByKey(string $key, array $relations = []): mixed
    {
        try {
            $memoKey = empty($relations) ? $key : $key.'::'.implode(',', $relations);

            if (! array_key_exists($memoKey, self::$modelMemo)) {
                self::$modelMemo[$memoKey] = BusinessSetting::where('key', $key)->with($relations)->first();
            }

            return self::$modelMemo[$memoKey];
        } catch (\Throwable $th) {
            return null;
        }
    }

    public function findBrandAsset(string $key): mixed
    {
        $memoKey = $key.'::storage';

        if (! array_key_exists($memoKey, self::$modelMemo)) {
            $rows = BusinessSetting::whereIn('key', ['logo', 'icon'])->with('storage')->get()->keyBy('key');
            self::$modelMemo['logo::storage'] = $rows->get('logo');
            self::$modelMemo['icon::storage'] = $rows->get('icon');
        }

        return self::$modelMemo[$memoKey];
    }

    public function findSystemLanguage(string $sessionKey): mixed
    {
        if (session()->has($sessionKey)) {
            return session($sessionKey);
        }

        $language = $this->findByKey('system_language');
        session()->put($sessionKey, $language);

        return $language;
    }

    public static function forgetModelMemo(): void
    {
        self::$modelMemo = [];
    }

    private function byKeysQuery(array $keys): mixed
    {
        return BusinessSetting::whereIn('key', $keys);
    }
}
