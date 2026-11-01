<?php

namespace App\Http\Controllers\Api\V1\DeliveryMan\Report;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\DeliveryMan\Report\EarningReportRequest;
use App\Http\Requests\DeliveryMan\Report\LoyaltyPointListRequest;
use App\Http\Resources\DeliveryMan\Report\EarningResource;
use App\Http\Resources\DeliveryMan\Report\EarningTransactionResource;
use App\Http\Resources\DeliveryMan\Report\IncomeStatementResource;
use App\Http\Resources\DeliveryMan\Report\LoyaltyPointResource;
use App\Http\Resources\DeliveryMan\Report\ParcelReturnEarningResource;
use App\Http\Resources\DeliveryMan\Report\ReferralEarningResource;
use App\Services\DeliveryMan\DeliverymanLoyaltyPointHistoryService;
use App\Services\DeliveryMan\DeliverymanReferralHistoryService;
use App\Services\Order\OrderTransactionService;
use App\Services\Parcel\ParcelReturnFeeService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningReportController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        protected OrderTransactionService $orderTransactionService,
        protected DeliverymanLoyaltyPointHistoryService $loyaltyPointService,
        protected DeliverymanReferralHistoryService $referralService,
        protected ParcelReturnFeeService $parcelReturnFeeService
    ) {}

    public function index(EarningReportRequest $request): JsonResponse
    {
        $filters = $this->reportFilters($request);
        $earnings = $this->orderTransactionService->getDeliveryManEarningList($filters, $this->pageParams($request));

        return $this->responseFormatter(config('response.default_200'), array_merge($this->totals($filters), [
            'type' => $filters['type'],
            'data' => EarningResource::collection($earnings),
            'pagination' => $this->paginateFormatter($earnings),
        ]));
    }

    public function loyalty(EarningReportRequest $request): JsonResponse
    {
        $filters = $this->reportFilters($request);
        $points = $this->loyaltyPointService->getConvertedList($filters, $this->pageParams($request));

        return $this->responseFormatter(config('response.default_200'), array_merge($this->totals($filters), [
            'type' => $filters['type'],
            'data' => LoyaltyPointResource::collection($points),
            'pagination' => $this->paginateFormatter($points),
        ]));
    }

    public function referral(EarningReportRequest $request): JsonResponse
    {
        $filters = $this->reportFilters($request);
        $referrals = $this->referralService->getList($filters, $this->pageParams($request));

        return $this->responseFormatter(config('response.default_200'), array_merge($this->totals($filters), [
            'type' => $filters['type'],
            'data' => ReferralEarningResource::collection($referrals),
            'pagination' => $this->paginateFormatter($referrals),
        ]));
    }

    public function loyaltyPoints(LoyaltyPointListRequest $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->loyaltyPointService->getList($this->reportFilters($request), $this->pageParams($request)),
            LoyaltyPointResource::class
        );
    }

    public function referralEarnings(EarningReportRequest $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->referralService->getList($this->reportFilters($request), $this->pageParams($request)),
            ReferralEarningResource::class
        );
    }

    public function parcelReturnEarnings(EarningReportRequest $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->parcelReturnFeeService->getDeliveryManList($this->reportFilters($request), $this->pageParams($request)),
            ParcelReturnEarningResource::class
        );
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->orderTransactionService->getIncomeStatement(['delivery_man_id' => $this->deliveryManId()], $this->pageParams($request)),
            IncomeStatementResource::class
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $filter = $request->query('filter', 'all_time');

        $filters = [
            'delivery_man_id' => $this->deliveryManId(),
            'filter' => $filter,
            'from' => $filter === 'custom' ? $request->input('from') : null,
            'to' => $filter === 'custom' ? $request->input('to') : null,
            'order_types' => $request->query('order_types', $request->query('order_type', ['take_away', 'delivery'])),
            'search' => $request->input('search'),
        ];

        $transactions = $this->orderTransactionService->deliveryManEarningTransactions($filters, $this->pageParams($request));

        return $this->responseFormatter(config('response.default_200'), [
            'summary' => $this->orderTransactionService->deliveryManEarningSummary($filters),
            'trends' => $this->orderTransactionService->deliveryManEarningTrend($filters),
            'transactions' => [
                'data' => EarningTransactionResource::collection($transactions),
                'pagination' => $this->paginateFormatter($transactions),
            ],
        ]);
    }

    private function reportFilters(EarningReportRequest $request): array
    {
        return $request->filters() + ['delivery_man_id' => $this->deliveryManId()];
    }

    private function totals(array $filters): array
    {
        return $this->orderTransactionService->deliveryManTotals($filters) + [
            'total_loyalty_point_earning' => $this->loyaltyPointService->convertedTotal($filters),
            'total_referal' => $this->referralService->earnedTotal($filters),
        ];
    }
}
