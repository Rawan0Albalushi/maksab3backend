<?php

namespace App\Http\Controllers\Api\V1\Vendor\Subscription;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Report\DateRangeRequest;
use App\Http\Resources\Vendor\Subscription\TransactionResource;
use App\Services\Payment\SubscriptionTransactionService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;

class TransactionController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly SubscriptionTransactionService $subscriptionTransactionService) {}

    public function index(DateRangeRequest $request): JsonResponse
    {
        $transactions = $this->subscriptionTransactionService->getStoreList(
            filters: $request->filters() + ['store_id' => $this->vendorStoreId($request)],
            paginate: $this->pageParams($request),
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => TransactionResource::collection($transactions),
            'pagination' => $this->paginateFormatter($transactions),
        ]);
    }
}
