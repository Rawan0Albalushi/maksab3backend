<?php

namespace App\Http\Controllers\Api\V1\Customer\Wallet;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Customer\Wallet\BonusResource;
use App\Services\Payment\WalletBonusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BonusController extends BaseApiController
{
    public function __construct(
        private readonly WalletBonusService $walletBonusService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $bonuses = $this->walletBonusService->getRunningList(
            ['per_page' => $this->perPage($request), 'page' => $this->page($request)]
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => BonusResource::collection($bonuses),
            'pagination' => $this->paginateFormatter($bonuses),
        ]);
    }
}
