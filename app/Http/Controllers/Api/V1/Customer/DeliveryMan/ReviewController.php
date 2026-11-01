<?php

namespace App\Http\Controllers\Api\V1\Customer\DeliveryMan;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\DeliveryMan\ReviewStoreRequest;
use App\Http\Resources\Customer\DeliveryMan\ReviewResource;
use App\Services\DeliveryMan\DmReviewService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(protected DmReviewService $reviewService) {}

    public function index(Request $request, mixed $deliveryManId): JsonResponse
    {
        $reviews = $this->reviewService->getList(
            ['delivery_man_id' => $deliveryManId],
            $this->pageParams($request)
        );

        return $this->pagedResponse($reviews, ReviewResource::class);
    }

    public function rating(mixed $deliveryManId): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), [
            'average_rating' => $this->reviewService->averageRating($deliveryManId),
        ]);
    }

    public function store(ReviewStoreRequest $request): JsonResponse
    {
        $payload = $request->payload() + ['user_id' => $request->user()->id];

        if ($this->reviewService->alreadyReviewed($payload)) {
            return $this->errorResponse(config('response.already_exists_409'), translate('messages.Already submitted'), 'review');
        }

        $this->reviewService->create($payload);

        return $this->responseFormatter(
            ['message' => translate('messages.Review submitted successfully')] + config('response.default_store_201')
        );
    }
}
