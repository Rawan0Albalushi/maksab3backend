<?php

namespace App\Services\Store;

use App\CentralLogics\Helpers;
use App\Models\Store;
use App\Models\StoreConfig;
use App\Services\BaseService;
use App\Support\Notification\NotificationMessages;
use App\Support\Notification\SendNotification;
use Illuminate\Support\Facades\Log;

class StoreConfigService extends BaseService
{
    public function findLowStockWarningLevel(mixed $storeId): mixed
    {
        return StoreConfig::where('store_id', $storeId)
            ->where('show_low_stock_count', 1)
            ->value('minimum_stock_for_warning');
    }

    public function findOrNewForStore(mixed $storeId): StoreConfig
    {
        return StoreConfig::firstOrNew(['store_id' => $storeId]);
    }

    public function ensureForStore(mixed $storeId): void
    {
        $this->findOrNewForStore($storeId)->save();
    }

    public function toggleVerifiedSeller(Store $store, ?int $status = null): int
    {
        $storeConfig = $this->findOrNewForStore($store->id);
        $storeConfig->verified_seller = is_null($status) ? (int) ! ($storeConfig->verified_seller ?? 0) : (int) $status;
        if ((int) $storeConfig->verified_seller === 1) {
            $storeConfig->has_seen_verified_badge_popup = 0;
        }
        $storeConfig->save();

        Helpers::deleteCacheData('verified_seller_eligible_providers_');
        Helpers::deleteCacheData('verified_seller_eligible_stores_');

        $this->notifyVendorOfVerifiedSellerChange($store, (int) $storeConfig->verified_seller);

        return (int) $storeConfig->verified_seller;
    }

    public function markVerifiedBadgePopupSeen(Store $store): int
    {
        $storeConfig = $this->findOrNewForStore($store->id);
        $storeConfig->has_seen_verified_badge_popup = 1;
        $storeConfig->save();

        return (int) $storeConfig->has_seen_verified_badge_popup;
    }

    private function notifyVendorOfVerifiedSellerChange(Store $store, int $verifiedSeller): void
    {
        try {
            $vendor = $store->vendor;
            if (isset($vendor->firebase_token) && $vendor->firebase_token != '@') {
                $data = $verifiedSeller === 1
                    ? NotificationMessages::verifiedSeller()
                    : NotificationMessages::verifiedSellerRemoved();

                SendNotification::pushToVendorPanel($vendor->id, $vendor->firebase_token, $data);
            }
        } catch (\Throwable $th) {
            Log::error('store.store_config_service.notify_vendor_of_verified_seller_change_failed', [
                'error' => $th->getMessage(),
                'file' => $th->getFile().':'.$th->getLine(),
            ]);
        }
    }
}
