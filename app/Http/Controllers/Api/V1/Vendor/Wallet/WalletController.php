<?php

namespace App\Http\Controllers\Api\V1\Vendor\Wallet;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Wallet\CollectCashPaymentRequest;
use App\Http\Resources\Common\Payment\WalletPaymentResource;
use App\Services\Payment\AccountTransactionService;
use App\Services\Vendor\VendorService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly VendorService $vendorService,
        private readonly AccountTransactionService $accountTransactionService
    ) {}

    public function collectCashPayment(CollectCashPaymentRequest $request): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), [
            'redirect_link' => $this->vendorService->collectCashPaymentLink(
                $request->input('vendor'),
                $this->vendorStore($request),
                $request->payload()
            ),
        ]);
    }

    public function adjust(Request $request): JsonResponse
    {
        return $this->serviceResponse($this->vendorService->adjustWallet($this->vendorId($request)));
    }

    public function payments(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->accountTransactionService->getVendorCollectedList([
                'vendor_id' => $this->vendorId($request),
                'search' => $request->input('search'),
            ], $this->pageParams($request)),
            WalletPaymentResource::class
        );
    }
}
