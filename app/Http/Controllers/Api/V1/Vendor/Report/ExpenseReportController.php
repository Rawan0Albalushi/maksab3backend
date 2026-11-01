<?php

namespace App\Http\Controllers\Api\V1\Vendor\Report;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Report\DateRangeRequest;
use App\Http\Resources\Vendor\Report\ExpenseResource;
use App\Services\Order\ExpenseService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;

class ExpenseReportController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly ExpenseService $expenseService) {}

    public function index(DateRangeRequest $request): JsonResponse
    {
        $expenses = $this->expenseService->getStoreList(
            filters: $request->filters() + ['store_id' => $this->vendorStoreId($request)],
            paginate: $this->pageParams($request),
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ExpenseResource::collection($expenses),
            'pagination' => $this->paginateFormatter($expenses),
        ]);
    }
}
