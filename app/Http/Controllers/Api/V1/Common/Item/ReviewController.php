<?php

namespace App\Http\Controllers\Api\V1\Common\Item;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Common\Item\ReviewStoreRequest;
use App\Http\Resources\Common\Item\ReviewResource;
use App\Services\Item\ItemService;
use App\Services\Item\ReviewService;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends BaseApiController
{
    public function __construct(
        private readonly ReviewService $reviewService,
        private readonly ItemService $itemService,
        private readonly OrderService $orderService
    ) {
    }

    public function index(Request $request, mixed $itemId): JsonResponse
    {
        $reviews = $this->reviewService->getList(
            filters: ['item_id' => $itemId],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'rating_summary' => $this->reviewService->ratingSummary($itemId),
            'data' => ReviewResource::collection($reviews),
            'pagination' => $this->paginateFormatter($reviews),
        ]);
    }

    public function rating(mixed $itemId): JsonResponse
    {
        return $this->itemService->exists($itemId)
            ? $this->responseFormatter(config('response.default_200'), [
                'rating' => $this->reviewService->overallRating($itemId),
            ])
            : $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'item', 'message' => translate('No data found')],
            ]);
    }

    public function store(ReviewStoreRequest $request): JsonResponse
    {
        $payload = $request->payload() + ['user_id' => $request->user()->id];

        if ($this->reviewService->alreadyReviewed($payload['item_id'], $payload['user_id'], $payload['order_id'])) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'review', 'message' => translate('messages.Already submitted')],
            ]);
        }

        $moduleId = $this->orderService->markReviewed($payload['order_id']);

        $this->reviewService->create($payload + ['module_id' => $moduleId]);
        $this->itemService->applyReviewRating($payload['item_id'], (int) $payload['rating']);

        return $this->responseFormatter(config('response.default_store_201'), [
            'message' => translate('messages.Review submitted successfully'),
        ]);
    }
}
