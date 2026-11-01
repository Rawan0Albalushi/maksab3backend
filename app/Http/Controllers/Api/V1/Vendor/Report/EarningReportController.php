<?php

namespace App\Http\Controllers\Api\V1\Vendor\Report;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Vendor\Report\EarningTransactionResource;
use App\Services\Order\OrderTransactionService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningReportController extends BaseApiController
{
    use ApiRequestContextTrait;

    private const TYPE_EXPENSE = 'expense';

    private const TYPE_SUBSCRIPTION = 'subscription';

    public function __construct(private readonly OrderTransactionService $orderTransactionService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $this->reportFilters($request);

        if (! $filters['store_id']) {
            return $this->responseFormatter(config('response.unauthorized_401'));
        }

        $transactions = match ($request->query('type', 'earning')) {
            self::TYPE_EXPENSE => $this->orderTransactionService->storeExpenseTransactions($filters, $this->pageParams($request)),
            self::TYPE_SUBSCRIPTION => $this->orderTransactionService->storeSubscriptionTransactions($filters, $this->pageParams($request)),
            default => $this->orderTransactionService->storeEarningTransactions($filters, $this->pageParams($request)),
        };

        return $this->responseFormatter(config('response.default_200'), [
            'summary' => $this->orderTransactionService->storeEarningSummary($filters),
            'trends' => $this->orderTransactionService->storeEarningTrend($filters),
            'data' => EarningTransactionResource::collection($transactions),
            'pagination' => $this->paginateFormatter($transactions),
        ]);
    }

    private function reportFilters(Request $request): array
    {
        $filter = $request->query('filter', 'all_time');

        return [
            'store_id' => $this->vendorStoreId($request),
            'filter' => $filter,
            'from' => $filter === 'custom' ? $request->query('from') : null,
            'to' => $filter === 'custom' ? $request->query('to') : null,
            'order_types' => $request->query('order_types', $request->query('order_type', ['take_away', 'delivery'])),
            'search' => $request->query('search'),
        ];
    }
}
