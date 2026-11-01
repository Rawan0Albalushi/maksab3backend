<?php

namespace App\Http\Controllers\Api\V1\Customer\Wallet;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Wallet\FundStoreRequest;
use App\Services\Payment\WalletPaymentService;
use Illuminate\Http\JsonResponse;

class FundController extends BaseApiController
{
    public function __construct(
        private readonly WalletPaymentService $walletPaymentService
    ) {
    }

    public function store(FundStoreRequest $request): JsonResponse
    {
        if (! $this->walletPaymentService->digitalPaymentEnabled()) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'digital_payment', 'message' => translate('messages.Digital payment is disable')],
            ]);
        }

        $redirectLink = $this->walletPaymentService->create(
            $request->payload() + ['user_id' => $request->user()->id]
        );

        return $redirectLink
            ? $this->responseFormatter(config('response.default_store_201'), ['redirect_link' => $redirectLink])
            : $this->responseFormatter(config('response.default_404'));
    }
}
