<?php

namespace App\Http\Controllers\Api\V1\Vendor\Disbursement;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Common\Payment\WithdrawMethodDefaultRequest;
use App\Http\Requests\Common\Payment\WithdrawMethodIdRequest;
use App\Http\Requests\Vendor\Disbursement\WithdrawMethodStoreRequest;
use App\Http\Resources\Common\Payment\DisbursementMethodResource;
use App\Http\Resources\Common\Payment\WithdrawalMethodResource;
use App\Services\Payment\DisbursementWithdrawalMethodService;
use App\Services\Payment\WithdrawalMethodService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawMethodController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly DisbursementWithdrawalMethodService $disbursementMethodService,
        private readonly WithdrawalMethodService $withdrawalMethodService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->disbursementMethodService->getList(
                $this->storeFilters($request) + ['search' => $request->input('search')],
                $this->pageParams($request)
            ),
            DisbursementMethodResource::class
        );
    }

    public function withdrawalMethods(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->withdrawalMethodService->getActiveList($this->pageParams($request)),
            WithdrawalMethodResource::class
        );
    }

    public function store(WithdrawMethodStoreRequest $request): JsonResponse
    {
        $method = $this->disbursementMethodService->create($request->payload() + $this->storeFilters($request));

        if (! $method) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'withdraw_method_id');
        }

        return $this->responseFormatter(['message' => translate('Added successfully')] + config('response.default_store_201'));
    }

    public function makeDefault(WithdrawMethodDefaultRequest $request): JsonResponse
    {
        $method = $this->disbursementMethodService->makeDefault(
            $request->input('id'),
            $this->storeFilters($request),
            $request->input('is_default')
        );

        if (! $method) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'id');
        }

        return $this->responseFormatter(['message' => translate('Updated successfully')] + config('response.default_update_200'));
    }

    public function destroy(WithdrawMethodIdRequest $request): JsonResponse
    {
        $deleted = $this->disbursementMethodService->delete($request->input('id'), $this->storeFilters($request));

        if (! $deleted) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'id');
        }

        return $this->responseFormatter(['message' => translate('Deleted successfully')] + config('response.default_delete_200'));
    }

    private function storeFilters(Request $request): array
    {
        return ['store_id' => $this->vendorStoreId($request)];
    }
}
