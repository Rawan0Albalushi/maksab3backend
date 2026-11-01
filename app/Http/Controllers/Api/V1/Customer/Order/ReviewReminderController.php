<?php

namespace App\Http\Controllers\Api\V1\Customer\Order;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Order\ReviewCancelRequest;
use App\Http\Resources\Customer\Order\ReviewReminderResource;
use App\Services\Order\OrderReferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewReminderController extends BaseApiController
{
    public function __construct(
        private readonly OrderReferenceService $orderReferenceService
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $order = $this->orderReferenceService->pendingReviewOrder($request->user()->id);

        return $this->responseFormatter(
            config('response.default_200'),
            new ReviewReminderResource($order, $this->orderReferenceService->reviewImages($order))
        );
    }

    public function cancel(ReviewCancelRequest $request): JsonResponse
    {
        return $this->orderReferenceService->cancelReview($request->input('order_id'), $request->user()->id)
            ? $this->responseFormatter(config('response.default_update_200'))
            : $this->responseFormatter(config('response.default_404'));
    }
}
