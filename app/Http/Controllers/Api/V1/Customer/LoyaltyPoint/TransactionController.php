<?php

namespace App\Http\Controllers\Api\V1\Customer\LoyaltyPoint;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\LoyaltyPoint\PointTransferRequest;
use App\Http\Resources\Customer\LoyaltyPoint\TransactionResource;
use App\Services\Payment\LoyaltyPointTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends BaseApiController
{
    public function __construct(
        private readonly LoyaltyPointTransactionService $loyaltyPointTransactionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $transactions = $this->loyaltyPointTransactionService->getList(
            filters: ['user_id' => $request->user()->id],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => TransactionResource::collection($transactions),
            'pagination' => $this->paginateFormatter($transactions),
        ]);
    }

    public function transfer(PointTransferRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->loyaltyPointTransactionService->canTransfer($user->loyalty_point, $request->input('point'))) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'point', 'message' => translate('messages.Insufficient point')],
            ]);
        }

        $transferred = $this->loyaltyPointTransactionService->transferToWallet(
            $user->id,
            $request->input('point'),
            $request->input('reference'),
            $user->getRawOriginal('email')
        );

        return $transferred
            ? $this->responseFormatter(config('response.default_200'), [
                'message' => translate('messages.Point to wallet transfer successfully'),
            ])
            : $this->responseFormatter(config('response.default_500'), errors: [
                ['code' => 'customer_wallet', 'message' => translate('messages.failed_to_transfer')],
            ]);
    }
}
