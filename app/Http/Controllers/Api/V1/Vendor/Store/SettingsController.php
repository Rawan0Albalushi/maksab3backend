<?php

namespace App\Http\Controllers\Api\V1\Vendor\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Store\StoreBasicInfoUpdateRequest;
use App\Http\Requests\Vendor\Store\StoreSetupUpdateRequest;
use App\Services\Store\StoreService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;

class SettingsController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly StoreService $storeService) {}

    public function updateBasicInfo(StoreBasicInfoUpdateRequest $request): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $this->storeService->updateProfile($store, $request->payload());

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function updateSetup(StoreSetupUpdateRequest $request): JsonResponse
    {
        $store = $this->vendorStore($request);

        if (! $store) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $this->storeService->updateOperationSetup($store, $request->payload());

        return $this->responseFormatter(config('response.default_update_200'));
    }
}
