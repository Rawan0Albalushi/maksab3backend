<?php

namespace App\Http\Controllers\Api\V1\Vendor\Disbursement;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Disbursement\WithdrawRequestStoreRequest;
use App\Http\Resources\Common\Payment\WithdrawRequestResource;
use App\Services\Payment\WithdrawRequestService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawRequestController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly WithdrawRequestService $withdrawRequestService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->withdrawRequestService->getVendorList(['vendor_id' => $this->vendorId($request)], $this->pageParams($request)),
            WithdrawRequestResource::class
        );
    }

    public function store(WithdrawRequestStoreRequest $request): JsonResponse
    {
        return $this->serviceResponse(
            $this->withdrawRequestService->createForVendor($request->input('vendor'), $request->payload()),
            config('response.default_store_201')
        );
    }
}
