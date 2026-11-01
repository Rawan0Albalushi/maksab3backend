<?php

namespace App\Http\Controllers\Api\V1\DeliveryMan\Disbursement;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\DeliveryMan\Disbursement\WithdrawRequestStoreRequest;
use App\Http\Resources\Common\Payment\WithdrawRequestResource;
use App\Services\Payment\WithdrawRequestService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawRequestController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(protected WithdrawRequestService $withdrawRequestService) {}

    public function index(Request $request): JsonResponse
    {
        $requests = $this->withdrawRequestService->getDeliveryManList(
            ['delivery_man_id' => $this->deliveryManId()],
            $this->pageParams($request)
        );

        return $this->pagedResponse($requests, WithdrawRequestResource::class);
    }

    public function store(WithdrawRequestStoreRequest $request): JsonResponse
    {
        $result = $this->withdrawRequestService->createForDeliveryMan(
            $this->deliveryMan()?->wallet,
            $request->payload()
        );

        return $this->serviceResponse($result, config('response.default_store_201'));
    }
}
