<?php

namespace App\Http\Controllers\Api\V1\Vendor\Item;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Item\ReviewReplyRequest;
use App\Http\Resources\Vendor\Item\StoreReviewResource;
use App\Services\Item\ReviewService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemReviewController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly ReviewService $reviewService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->reviewService->getVendorList(
                ['store_id' => $this->vendorStoreId($request), 'search' => $request->input('search')],
                $this->pageParams($request)
            ),
            StoreReviewResource::class
        );
    }

    public function updateReply(ReviewReplyRequest $request): JsonResponse
    {
        $review = $this->reviewService->updateReply($request->validated(), $this->vendorStoreId($request));

        if (! $review) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'id');
        }

        return $this->responseFormatter(
            ['message' => translate('Updated successfully')] + config('response.default_update_200')
        );
    }
}
