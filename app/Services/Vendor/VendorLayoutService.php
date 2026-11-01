<?php

namespace App\Services\Vendor;

use App\CentralLogics\Helpers;
use App\Services\System\BusinessSettingService;

/**
 * View data for the vendor layout shell (layouts/vendor/app.blade.php).
 *
 * Replaces the raw <?php block that opened the file -- text direction, country
 * code, module type, the verified-badge popup flags -- plus the @php blocks
 * that resolved the chrome version and the FCM credentials.
 */
class VendorLayoutService
{
    public function build(): array
    {
        $store = Helpers::get_store_data();
        $moduleType = $store?->module_type;

        return [
            'site_direction' => $this->siteDirection(),
            'countryCode' => app(BusinessSettingService::class)->value('country') ?? 'auto',
            'moduleType' => $moduleType,
            'verifiedBadgePopupShow' => (bool) ($store?->storeConfig?->verified_seller
                && ! $store?->storeConfig?->has_seen_verified_badge_popup),
            'verifiedBadgePopupLabel' => $moduleType === 'rental'
                ? translate('messages.Provider')
                : translate('messages.Store'),
            'fcmCredentials' => (array) (app(BusinessSettingService::class)->value('fcm_credentials') ?: []),
            'storeId' => Helpers::get_store_id(),
            // Mirrors layouts/admin/app.blade.php: the order pop-up is gated on the same
            // two global business settings the admin panel uses -- admin_order_notification
            // as the on/off switch and order_notification_type for how it is delivered.
            // Default 'manual' matches the admin layout (the vendor side previously
            // defaulted to 'firebase', which disagreed with admin for an unset value).
            'admin_order_notification' => app(BusinessSettingService::class)->value('admin_order_notification') ?? 0,
            'order_notification_type' => app(BusinessSettingService::class)->value('order_notification_type') ?? 'manual',
        ] + $this->chrome($moduleType);
    }

    /**
     * Demo mode keeps direction in its own session key so the public demo can be
     * flipped without affecting a real vendor's saved preference.
     */
    private function siteDirection(): string
    {
        if (getEnvMode() === 'demo') {
            return session()->get('site_direction_vendor') ?: 'ltr';
        }

        return session()->has('vendor_site_direction')
            ? session()->get('vendor_site_direction')
            : 'ltr';
    }

    /**
     * v2 chrome is opt-out via config('layout.version'); it only applies to
     * stores that resolve to a module type.
     */
    private function chrome(?string $moduleType): array
    {
        $features = (array) config('layout.features', []);

        $useV2 = match (config('layout.version', 'auto')) {
            'v1' => false,
            default => isset($moduleType),
        };

        return [
            'layout_features' => $features,
            'use_v2_chrome' => $useV2,
            'layoutBodyClass' => trim(implode(' ', array_filter([
                'footer-offset',
                $useV2 ? 'v2-chrome' : null,
                $useV2 && ($features['pin'] ?? true) === false ? 'layout-no-pin' : null,
            ]))),
        ];
    }
}
