<?php

namespace App\Http\Controllers\Api\V1\DeliveryMan\Notification;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\System\NotificationResource;
use App\Services\System\NotificationService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(protected NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        $deliveryMan = $this->deliveryMan();

        $notifications = $this->notificationService->getDeliveryManFeed([
            'delivery_man_id' => $deliveryMan->id,
            'zone_id' => $deliveryMan->zone_id,
            'is_ride' => $deliveryMan->is_ride,
        ], $this->pageParams($request));

        return $this->pagedResponse($notifications, NotificationResource::class);
    }
}
