<?php

namespace App\Services\Store;

use App\Models\StoreWallet;
use App\Services\BaseService;

class StoreWalletService extends BaseService
{
    public function findOrNewForVendor(mixed $vendorId): StoreWallet
    {
        return $this->forVendorQuery($vendorId)->firstOrNew(['vendor_id' => $vendorId]);
    }
    public function findForVendor(mixed $vendorId): ?StoreWallet
    {
        return $this->forVendorQuery($vendorId)->first();
    }

    private function forVendorQuery(mixed $vendorId): mixed
    {
        return StoreWallet::where('vendor_id', $vendorId);
    }
}
