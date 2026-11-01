<?php

namespace App\Http\Controllers\Api\V1\Customer\Order;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Order\RefundStoreRequest;
use App\Http\Resources\Common\System\ReasonResource;
use App\Services\Order\RefundReasonService;
use App\Services\Order\RefundService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly RefundService $refundService,
        private readonly RefundReasonService $refundReasonService
    ) {}

    public function store(RefundStoreRequest $request): JsonResponse
    {
        if (Helpers::get_business_settings('refund_active_status', false) == false) {
            return $this->errorResponse(config('response.forbidden_403'), translate('You can not request for a refund'), 'order');
        }

        $order = $this->refundService->findRefundable([
            'user_id' => auth('api')->id(),
            'order_id' => $request->input('order_id'),
        ]);

        if (! $order) {
            return $this->responseFormatter(config('response.default_404'));
        }

        if ($order->order_status !== 'delivered' || $order->payment_status !== 'paid') {
            return $this->errorResponse(config('response.forbidden_403'), translate('Something went wrong'), 'order');
        }

        $this->refundService->create($order, $request->payload());

        return $this->responseFormatter(config('response.default_store_201'));
    }

    public function reasons(Request $request): JsonResponse
    {
        return $this->pagedResponse($this->refundReasonService->getList([], $this->pageParams($request)), ReasonResource::class);
    }
}
