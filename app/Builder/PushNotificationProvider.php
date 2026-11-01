<?php

namespace App\Builder;

use App\Models\User;
use Modules\Builder\Contracts\PushNotificationProvider as PushNotificationProviderContract;

class PushNotificationProvider implements PushNotificationProviderContract
{
    public function storeCustomerToken(int $customerId, string $token): void
    {
        User::query()
            ->where('id', $customerId)
            ->update(['cm_firebase_token' => $token]);
    }

    public function clearCustomerToken(int $customerId): void
    {
        User::query()
            ->where('id', $customerId)
            ->update(['cm_firebase_token' => null]);
    }
}
