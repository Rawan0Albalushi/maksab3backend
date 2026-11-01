<?php

namespace App\Services\System;

use App\Models\UserNotification;
use App\Services\BaseService;
use App\Support\Notification\SendNotification;

class UserNotificationService extends BaseService
{
    public function feedQuery(mixed $ownerColumn, mixed $ownerId, mixed $dateColumn, mixed $cutoff, mixed $sourceFlag): mixed
    {
        return UserNotification::where($ownerColumn, $ownerId)
            ->where($dateColumn, '>=', $cutoff)
            ->selectRaw('id, ' . $sourceFlag . ' as feed_source');
    }

    public function getByIds(array $ids, array $columns): mixed
    {
        return UserNotification::whereIn('id', $ids)->select($columns)->get()->keyBy('id');
    }

    public function record(mixed $userId, array $data): void
    {
        SendNotification::saveNotificationForCustomer($userId, $data);
    }

    public function recordForDeliveryMan(mixed $deliveryManId, array $data): void
    {
        SendNotification::saveNotificationForDeliveryMan($deliveryManId, $data);
    }

}
