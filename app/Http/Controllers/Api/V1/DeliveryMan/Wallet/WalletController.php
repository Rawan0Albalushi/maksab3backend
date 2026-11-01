<?php

namespace App\Http\Controllers\Api\V1\DeliveryMan\Wallet;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\DeliveryMan\Wallet\CollectCashPaymentRequest;
use App\Http\Requests\DeliveryMan\Wallet\LoyaltyPointConvertRequest;
use App\Http\Resources\DeliveryMan\Wallet\ProvidedEarningResource;
use App\Http\Resources\Common\Payment\WalletPaymentResource;
use App\Services\DeliveryMan\DeliverymanLoyaltyPointHistoryService;
use App\Services\DeliveryMan\DeliveryManWalletService;
use App\Services\Payment\AccountTransactionService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        protected DeliveryManWalletService $walletService,
        protected AccountTransactionService $accountTransactionService,
        protected DeliverymanLoyaltyPointHistoryService $loyaltyPointService
    ) {}

    public function collectCashPayment(CollectCashPaymentRequest $request): JsonResponse
    {
        $redirectLink = $this->walletService->collectCashPaymentLink($this->deliveryMan(), $request->payload());

        return $this->responseFormatter(config('response.default_200'), ['redirect_link' => $redirectLink]);
    }

    public function adjust(): JsonResponse
    {
        return $this->serviceResponse($this->walletService->adjust($this->deliveryManId()));
    }

    public function payments(Request $request): JsonResponse
    {
        $transactions = $this->accountTransactionService->getDeliveryManCollectedList([
            'delivery_man_id' => $this->deliveryManId(),
            'search' => $request->input('search'),
        ], $this->pageParams($request));

        return $this->pagedResponse($transactions, WalletPaymentResource::class);
    }

    public function providedEarnings(Request $request): JsonResponse
    {
        $earnings = $this->walletService->getProvidedEarningList([
            'delivery_man_id' => $this->deliveryManId(),
            'search' => $request->input('search'),
        ], $this->pageParams($request));

        return $this->pagedResponse($earnings, ProvidedEarningResource::class);
    }

    public function convertLoyaltyPoints(LoyaltyPointConvertRequest $request): JsonResponse
    {
        $deliveryMan = $this->deliveryMan();
        $points = $request->input('points');

        if (Helpers::get_business_settings('dm_loyality_point_status') != 1) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.Loyalty point is disabled'), 'points');
        }

        $minimumPoints = Helpers::get_business_settings('dm_min_loyality_point_to_convert');

        if ($minimumPoints > $points) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('You need to have at least').' '.$minimumPoints.' '.translate('points to convert'),
                'points'
            );
        }

        if ($deliveryMan->loyalty_point < $points) {
            return $this->errorResponse(config('response.forbidden_403'), translate('You have insufficient points'), 'points');
        }

        return $this->serviceResponse(
            $this->loyaltyPointService->convert($deliveryMan->id, $points),
            ['message' => translate('messages.Loyalty point converted successfully')] + config('response.default_update_200')
        );
    }
}
