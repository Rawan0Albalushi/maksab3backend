<?php

namespace App\Services\System;

use App\Models\DataSetting;
use App\Services\BaseService;
use App\Support\Cache\ApiCache;

class DataSettingService extends BaseService
{
    private static array $serviceFlagMemo = [];

    private const REACT_LANDING_TYPES = ['react_landing_page', 'module_home_page_data', 'module_vendor_registration_data'];
    private const APP_DOWNLOAD_KEYS = ['download_user_app_section_status', 'download_user_app_title'];
    private const MAINTENANCE_KEYS = ['maintenance_system_setup', 'maintenance_duration_setup', 'maintenance_message_setup'];
    private const RIDE_SHARE_CONFIG_KEYS = [
        'rider_can_review_customer', 'safety_feature_status', 'ride_safety_delay_time', 'ride_safety_delay_time_format',
        'safety_feature_after_ride_complete_status', 'safety_feature_after_ride_complete_time', 'safety_feature_after_ride_complete_time_format',
        'emergency_govt_number', 'ride_commission', 'search_radius', 'rider_completion_radius', 'bid_on_fare', 'rider_referal_status',
        'ride_otp_confirmation', 'ride_request_active_time', 'toggle_rider_registration', 'show_rider_earning', 'rider_level_status',
        'rider_tips_status', 'cash_in_hand_overflow_rider', 'rider_max_cash_in_hand', 'min_amount_to_pay_rider', 'rider_loyality_point_status',
        'rider_loyality_point_conversion_rate', 'rider_min_loyality_point_to_convert', 'rider_referal_amount', 'rider_referal_bonus',
        'customer_route_preference',
    ];
    private const LANDING_PAGE_TYPE = 'admin_landing_page';
    private const PRO_TERMS_TYPE = 'pro_customer_terms';
    private const PRO_BENEFITS_TYPE = 'pro_customer_benefits';
    private const PRO_TERMS_KEYS = ['page_title', 'page_description', 'page_status', 'page_image'];
    private const PRO_BENEFIT_STATUS_KEYS = ['discount_status', 'delivery_fee_status', 'coupon_status', 'discount_setup_mode'];
    private const PRO_TERMS_TRANSLATION_KEYS = [
        'page_title' => 'pro_terms_page_title',
        'page_description' => 'pro_terms_page_description',
    ];
    public function getReactLandingSettings(): array
    {
        return $this->keyedSettings(
            $this->storedQuery()->with('translations')->whereIn('type', self::REACT_LANDING_TYPES)->get()
        );
    }
    public function getFlutterLandingSettings(): array
    {
        return $this->keyedSettings(
            $this->storedQuery()->with('translations')->where('type', 'flutter_landing_page')->get()
        );
    }
    public function getAppDownloadSettings(): array
    {
        return $this->keyedSettings(
            $this->storedQuery()->with('translations')
                ->where('type', 'app_settings')
                ->whereIn('key', self::APP_DOWNLOAD_KEYS)
                ->get()
        );
    }
    public function getUserAppDownloadLinks(): array
    {
        $links = ApiCache::remember('data_settings', 'flutter_landing_page', function () {
            return $this->valuesByTypeAndKeys('flutter_landing_page', ['download_user_app_links'])->toArray();
        });

        return json_decode($links['download_user_app_links'] ?? '', true) ?: [];
    }
    public function getMaintenanceMode(): array
    {
        return ApiCache::remember('data_settings', 'maintenance_mode', function () {
            return $this->valuesByTypeAndKeys('maintenance_mode', self::MAINTENANCE_KEYS)
                ->map(fn ($value) => json_decode($value, true))
                ->toArray();
        });
    }
    public function getRideShareConfigs(): mixed
    {
        return DataSetting::whereIn('key', self::RIDE_SHARE_CONFIG_KEYS)
            ->where('type', RIDE_SHARE_BUSINESS_SETTINGS)
            ->pluck('value', 'key');
    }
    public function getRideSharePageRows(): mixed
    {
        return $this->storedQuery()->where('type', 'react_ride_share_page')->get()->keyBy('key');
    }
    public function getServiceModuleSettings(): mixed
    {
        return $this->byConditions(['type' => SERVICE_BUSINESS_SETTINGS])->pluck('value', 'key');
    }
    public function findStatusValue(string $key): mixed
    {
        return ApiCache::remember('data_settings', $key, function () use ($key) {
            return $this->byConditions(['key' => $key])->value('value');
        }) ?? 0;
    }
    public function findValueByKey(string $key): mixed
    {
        return $this->byConditions(['key' => $key])->first()->value ?? 0;
    }
    public function findValueByKeyAndType(string $key, mixed $type): mixed
    {
        return $this->byConditions(['key' => $key, 'type' => $type])->first()?->value;
    }
    public function getValuesByKey(string $key): array
    {
        return DataSetting::where('key', $key)
            ->whereNotNull('value')->where('value', '!=', 0)
            ->select('value')->get()
            ->map(fn ($row) => $row->value)->all();
    }
    public function proBenefitStatusFlags(): array
    {
        return $this->translationsUnscopedQuery()
            ->where('type', self::PRO_BENEFITS_TYPE)
            ->whereIn('key', self::PRO_BENEFIT_STATUS_KEYS)
            ->pluck('value', 'key')
            ->toArray();
    }
    public function proTermsPage(string $locale): array
    {
        $rows = $this->translationsUnscopedQuery()
            ->withStorage()
            ->with(['translations' => fn ($query) => $query
                ->where('locale', $locale)
                ->whereIn('key', array_values(self::PRO_TERMS_TRANSLATION_KEYS))
                ->select(TRANSLATION_RELATION_COLUMNS)])
            ->where('type', self::PRO_TERMS_TYPE)
            ->whereIn('key', self::PRO_TERMS_KEYS)
            ->get(['id', 'key', 'value'])
            ->keyBy('key');

        $imageRow = $rows->get('page_image');
        $imageValue = $imageRow?->getRawOriginal('value');

        return [
            'page_status' => (int) ($rows->get('page_status')?->getRawOriginal('value') ?? 0),
            'page_title' => $this->proTermsValue($rows->get('page_title'), self::PRO_TERMS_TRANSLATION_KEYS['page_title']),
            'page_description' => $this->proTermsValue($rows->get('page_description'), self::PRO_TERMS_TRANSLATION_KEYS['page_description']),
            'page_image' => $imageValue && $imageValue !== 'def.png' ? $imageValue : null,
            'page_image_disk' => $imageRow?->storage[0]?->value ?? 'public',
        ];
    }
    public function localizedContent(string $page, string $locale): string
    {
        $content = $this->translationsUnscopedQuery()
            ->with(['translations' => fn ($query) => $query->where('locale', $locale)->select(TRANSLATION_RELATION_COLUMNS)])
            ->where('type', self::LANDING_PAGE_TYPE)
            ->where('key', $page)
            ->first(['id', 'key', 'value']);

        if ($content && count($content->translations) > 0) {
            return (string) $content->translations[0]['value'];
        }

        return (string) ($content->value ?? '');
    }
    public function getValuesByType(string $type): array
    {
        return $this->byConditions(['type' => $type])->pluck('value', 'key')->toArray();
    }
    public function saveValue(string $key, string $type, mixed $value): void
    {
        $setting = DataSetting::firstOrNew(['key' => $key, 'type' => $type]);
        $setting->value = $value;
        $setting->save();
    }

    public function insertKeyIfMissing(string $key, string $type, mixed $value = null): bool
    {
        if (! DataSetting::where('key', $key)->where('type', $type)->exists()) {
            $this->saveValue($key, $type, $value);
        }

        return true;
    }

    public function findByTypeAndKey(string $type, string $key): ?DataSetting
    {
        return DataSetting::where('type', $type)->where('key', $key)->first();
    }

    public function getLandingMetaData(): mixed
    {
        return ApiCache::remember('landing', 'meta_data_'.app()->getLocale(), function () {
            return DataSetting::withStorage()->where('type', 'admin_landing_page')
                ->whereIn('key', ['meta_title', 'meta_description', 'meta_image'])
                ->get()
                ->keyBy('key');
        });
    }

    public function getLandingPolicyStatuses(): array
    {
        return ApiCache::remember('landing', 'policy_statuses_'.app()->getLocale(), function () {
            return DataSetting::where('type', 'admin_landing_page')
                ->whereIn('key', ['shipping_policy_status', 'refund_policy_status', 'cancellation_policy_status'])
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    public function findLoginUrlKey(string $type): mixed
    {
        $data = DataSetting::whereIn('key', [
            'store_employee_login_url',
            'store_login_url',
            'admin_employee_login_url',
            'admin_login_url',
        ])->pluck('key', 'value')->toArray();

        return array_search($type, $data);
    }

    public function serviceProviderCategoryStatus(): bool
    {
        return self::$serviceFlagMemo['service_provider_category_status']
            ??= $this->serviceFlagEnabled('service_provider_category_status');
    }

    public function serviceProviderVerifiedBadgeStatus(): bool
    {
        return self::$serviceFlagMemo['service_provider_verified_badge']
            ??= $this->serviceFlagEnabled('service_provider_verified_badge');
    }

    private function serviceFlagEnabled(string $key): bool
    {
        return (int) DataSetting::withoutTranslation()->where('type', SERVICE_BUSINESS_SETTINGS)
            ->where('key', $key)->value('value') === 1;
    }

    public function getEditableRowsByType(string $type): mixed
    {
        return $this->translationsUnscopedQuery()
            ->withStorage()
            ->with('translations')
            ->where('type', $type)
            ->get()
            ->keyBy('key');
    }

    private function storedQuery(): mixed
    {
        return DataSetting::withStorage();
    }
    private function translationsUnscopedQuery(): mixed
    {
        return DataSetting::withoutGlobalScope('translate');
    }
    private function valuesByTypeAndKeys(string $type, array $keys): mixed
    {
        return DataSetting::where('type', $type)->whereIn('key', $keys)->pluck('value', 'key');
    }
    private function byConditions(array $conditions): mixed
    {
        return DataSetting::where($conditions);
    }
    private function keyedSettings(mixed $rows): array
    {
        $settings = [];

        foreach ($rows as $row) {
            $settings[$row->key] = count($row->translations) > 0 ? $row->translations[0]['value'] : $row->value;

            if (isset($row->storage)) {
                $settings[$row->key . '_storage'] = $row?->storage[0]?->value ?? 'public';
            }
        }

        return $settings;
    }
    private function proTermsValue(?DataSetting $row, string $translationKey): ?string
    {
        if (! $row) {
            return null;
        }

        $translated = $row->translations->firstWhere('key', $translationKey)?->value;

        return $translated ?: $row->getRawOriginal('value');
    }
}
