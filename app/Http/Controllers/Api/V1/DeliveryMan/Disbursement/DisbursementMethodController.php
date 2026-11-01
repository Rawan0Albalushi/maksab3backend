<?php

namespace App\Http\Controllers\Api\V1\DeliveryMan\Disbursement;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Common\Payment\WithdrawMethodDefaultRequest;
use App\Http\Requests\Common\Payment\WithdrawMethodIdRequest;
use App\Http\Requests\DeliveryMan\Disbursement\DisbursementMethodStoreRequest;
use App\Http\Resources\Common\Payment\DisbursementMethodResource;
use App\Http\Resources\Common\Payment\WithdrawalMethodResource;
use App\Services\Payment\DisbursementWithdrawalMethodService;
use App\Services\Payment\WithdrawalMethodService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisbursementMethodController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        protected DisbursementWithdrawalMethodService $disbursementMethodService,
        protected WithdrawalMethodService $withdrawalMethodService
    ) {}

    public function withdrawalMethods(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->withdrawalMethodService->getActiveList($this->pageParams($request)),
            WithdrawalMethodResource::class
        );
    }

    public function index(Request $request): JsonResponse
    {
        $methods = $this->disbursementMethodService->getList([
            'delivery_man_id' => $this->deliveryManId(),
            'search' => $request->input('search'),
            'active_methods_only' => true,
        ], $this->pageParams($request));

        return $this->pagedResponse($methods, DisbursementMethodResource::class);
    }

    public function store(DisbursementMethodStoreRequest $request): JsonResponse
    {
        $method = $this->disbursementMethodService->create(
            $request->payload() + ['delivery_man_id' => $this->deliveryManId()]
        );

        if (! $method) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'withdraw_method_id');
        }

        return $this->responseFormatter(['message' => translate('Added successfully')] + config('response.default_store_201'));
    }

    public function makeDefault(WithdrawMethodDefaultRequest $request): JsonResponse
    {
        $method = $this->disbursementMethodService->makeDefault(
            $request->input('id'),
            ['delivery_man_id' => $this->deliveryManId()],
            $request->input('is_default')
        );

        if (! $method) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'id');
        }

        return $this->responseFormatter(['message' => translate('Updated successfully')] + config('response.default_update_200'));
    }

    public function destroy(WithdrawMethodIdRequest $request): JsonResponse
    {
        $deleted = $this->disbursementMethodService->delete(
            $request->input('id'),
            ['delivery_man_id' => $this->deliveryManId()]
        );

        if (! $deleted) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'id');
        }

        return $this->responseFormatter(['message' => translate('Deleted successfully')] + config('response.default_delete_200'));
    }
}
