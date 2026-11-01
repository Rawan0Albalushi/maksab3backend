<?php

namespace App\Http\Controllers\Api\V1\Customer\Order;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\System\ReasonResource;
use App\Services\Order\OrderCancelReasonService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancellationReasonController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly OrderCancelReasonService $orderCancelReasonService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->orderCancelReasonService->getList(['user_type' => $request->query('type')], $this->pageParams($request)),
            ReasonResource::class
        );
    }
}
