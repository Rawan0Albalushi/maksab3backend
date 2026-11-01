<?php

namespace App\Http\Controllers\Api\V1\Vendor\Report;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Vendor\Report\DisbursementResource;
use App\Services\Payment\DisbursementDetailService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisbursementReportController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly DisbursementDetailService $disbursementDetailService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = ['store_id' => $this->vendorStoreId($request)];
        $disbursements = $this->disbursementDetailService->getStoreList($filters, $this->pageParams($request));

        return $this->responseFormatter(config('response.default_200'), array_merge(
            $this->disbursementDetailService->storeStatusTotals($filters),
            [
                'complete_day' => $this->disbursementDetailService->storeWaitingDays(),
                'data' => DisbursementResource::collection($disbursements),
                'pagination' => $this->paginateFormatter($disbursements),
            ]
        ));
    }
}
