<?php

namespace App\Http\Controllers\Api\V1\DeliveryMan\Disbursement;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\DeliveryMan\Disbursement\DisbursementResource;
use App\Services\Payment\DisbursementDetailService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisbursementController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(protected DisbursementDetailService $disbursementDetailService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = ['delivery_man_id' => $this->deliveryManId()];
        $disbursements = $this->disbursementDetailService->getList($filters, $this->pageParams($request));

        return $this->responseFormatter(config('response.default_200'), array_merge(
            $this->disbursementDetailService->statusTotals($filters),
            [
                'complete_day' => (int) Helpers::get_business_settings('dm_disbursement_waiting_time', false),
                'data' => DisbursementResource::collection($disbursements),
                'pagination' => $this->paginateFormatter($disbursements),
            ]
        ));
    }
}
