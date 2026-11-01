<?php

namespace App\Services\Vendor;

use App\Models\VendorEmployee;
use App\Services\BaseService;

class VendorEmployeeService extends BaseService
{
    public function findByEmail(mixed $email): ?VendorEmployee
    {
        return VendorEmployee::where('email', $email)->first();
    }

    public function updateFirebaseToken(mixed $id, mixed $token): bool
    {
        return (bool) VendorEmployee::where('id', $id)->update(['firebase_token' => $token]);
    }
}
