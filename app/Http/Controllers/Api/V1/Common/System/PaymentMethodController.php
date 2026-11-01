<?php

namespace App\Http\Controllers\Api\V1\Common\System;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\System\OfflinePaymentMethodResource;
use App\Services\Payment\OfflinePaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends BaseApiController
{
    public function __construct(
        private readonly OfflinePaymentMethodService $offlinePaymentMethodService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $methods = $this->offlinePaymentMethodService->getActiveList(
            ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => OfflinePaymentMethodResource::collection($methods),
            'pagination' => $this->paginateFormatter($methods),
        ]);
    }
}
