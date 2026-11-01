<?php

namespace App\Http\Controllers\Api\V1\Customer\Notification;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Customer\Notification\NotificationResource;
use App\Services\System\NotificationService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $notifications = $this->notificationService->getCustomerFeed(
            filters: [
                'zone_ids' => $this->zoneIds($request),
                'user_id' => $request->user()->id,
            ],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => NotificationResource::collection($notifications),
            'pagination' => $this->paginateFormatter($notifications),
        ]);
    }
}
