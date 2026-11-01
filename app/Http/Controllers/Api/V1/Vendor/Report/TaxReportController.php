<?php

namespace App\Http\Controllers\Api\V1\Vendor\Report;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Report\TaxReportRequest;
use App\Http\Resources\Vendor\Report\TaxOrderResource;
use App\Services\Order\OrderService;
use App\Traits\Api\ApiRequestContextTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class TaxReportController extends BaseApiController
{
    use ApiRequestContextTrait;

    private const DATE_FORMAT = 'm/d/Y';

    public function __construct(private readonly OrderService $orderService) {}

    public function index(TaxReportRequest $request): JsonResponse
    {
        $filters = $this->taxFilters($request);
        $orders = $this->orderService->getStoreTaxOrderList($filters, $this->pageParams($request));

        return $this->responseFormatter(config('response.default_200'), array_merge(
            $this->orderService->storeTaxSummary($filters),
            [
                'tax_summary' => $this->orderService->storeTaxBreakdown($filters),
                'data' => TaxOrderResource::collection($orders),
                'pagination' => $this->paginateFormatter($orders),
            ]
        ));
    }

    private function taxFilters(TaxReportRequest $request): array
    {
        return $request->filters() + [
            'store_id' => $this->vendorStoreId($request),
            'start_date' => Carbon::createFromFormat(self::DATE_FORMAT, trim($request->input('from')))->startOfDay(),
            'end_date' => Carbon::createFromFormat(self::DATE_FORMAT, trim($request->input('to')))->endOfDay(),
        ];
    }
}
