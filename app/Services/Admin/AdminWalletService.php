<?php

namespace App\Services\Admin;

use App\Models\AdminWallet;
use App\Services\BaseService;

class AdminWalletService extends BaseService
{
    public function findOrNew(mixed $adminId): AdminWallet
    {
        return AdminWallet::firstOrNew(['admin_id' => $adminId]);
    }
}
