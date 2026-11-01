<?php

namespace App\Services\Customer;

use App\Models\Guest;
use App\Services\BaseService;

class GuestService extends BaseService
{
    public function create(array $data): ?Guest
    {
        $guest = new Guest;
        $guest->ip_address = $data['ip_address'] ?? null;
        $guest->fcm_token = $data['fcm_token'] ?? null;

        return $guest->save() ? $guest : null;
    }
}
