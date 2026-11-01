<?php

namespace App\Http\Controllers\Api\V1\Customer\Wallet;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Customer\Wallet\TransactionResource;
use App\Services\Payment\WalletTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends BaseApiController
{
    public function __construct(
        private readonly WalletTransactionService $walletTransactionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $transactions = $this->walletTransactionService->getList(
            filters: ['user_id' => $request->user()->id, 'type' => $request->query('type')],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => TransactionResource::collection($transactions),
            'pagination' => $this->paginateFormatter($transactions),
        ]);
    }
}
