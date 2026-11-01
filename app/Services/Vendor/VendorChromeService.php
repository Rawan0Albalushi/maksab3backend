<?php

namespace App\Services\Vendor;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\ModuleZone;
use App\Models\StoreWallet;
use App\Services\System\BusinessSettingService;
use App\Services\Chat\ConversationService;
use App\Services\Store\StoreWalletService;
use App\Services\Zone\ModuleZoneService;

/**
 * Shared data for the vendor panel chrome (header partials).
 *
 * These values were queried directly inside the header Blade partials, and both
 * the v1 and v2 headers issued the same queries independently on every page.
 * Resolved once per request here instead.
 */
class VendorChromeService
{
    private array $cache = [];

    public function systemLanguageSetting(): ?BusinessSetting
    {
        return $this->cache['lang_setting'] ??= app(BusinessSettingService::class)->findByKey('system_language');
    }

    /**
     * Decoded system language list. The v1 header decoded this twice per render.
     */
    public function systemLanguages(): array
    {
        if (array_key_exists('languages', $this->cache)) {
            return $this->cache['languages'];
        }

        $setting = $this->systemLanguageSetting();

        return $this->cache['languages'] = $setting ? (json_decode($setting->value, true) ?: []) : [];
    }

    public function unreadMessageCount(): int
    {
        if (array_key_exists('unread', $this->cache)) {
            return $this->cache['unread'];
        }

        $userId = Helpers::get_loggedin_user()?->id;

        return $this->cache['unread'] = $userId
            ? app(ConversationService::class)->unreadCountForUser($userId)
            : 0;
    }

    public function storeWallet(): ?StoreWallet
    {
        if (array_key_exists('wallet', $this->cache)) {
            return $this->cache['wallet'];
        }

        return $this->cache['wallet'] = app(StoreWalletService::class)->findForVendor(Helpers::get_vendor_id());
    }

    /**
     * The wallet pages create the row if it is missing. That get-or-create ran
     * inside the wallet Blade views, raw INSERT included.
     */
    public function storeWalletOrCreate(): StoreWallet
    {
        $wallet = $this->storeWallet();

        if (! $wallet) {
            // Direct assignment, not create(): StoreWallet declares no $fillable,
            // so mass assignment would be rejected.
            $wallet = new StoreWallet();
            $wallet->vendor_id = Helpers::get_vendor_id();
            $wallet->save();

            $this->cache['wallet'] = $wallet;
        }

        return $wallet;
    }

    /**
     * The module/zone pivot that drives the POS "saver delivery" options. This was queried
     * inside pos/_cart.blade.php, which renders from two paths (included by pos/index and
     * returned bare by POSController@cart_items), so a view composer covers both.
     */
    public function posCartModuleZone(): ?ModuleZone
    {
        if (array_key_exists('pos_module_zone', $this->cache)) {
            return $this->cache['pos_module_zone'];
        }

        $store = Helpers::get_store_data();

        return $this->cache['pos_module_zone'] = ($store && $store->zone_id)
            ? app(ModuleZoneService::class)->findForModuleAndZone($store->module_id, $store->zone_id)
            : null;
    }
}
