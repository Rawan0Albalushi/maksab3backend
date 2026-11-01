<?php

namespace App\Http\Controllers\Api\V1\Vendor\Notification;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\System\NotificationResource;
use App\Services\System\NotificationService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->notificationService->getVendorFeed([
                'vendor_id' => $this->vendorId($request),
                'zone_id' => $this->vendorStore($request)?->zone_id,
            ], $this->pageParams($request)),
            NotificationResource::class
        );
    }
}
