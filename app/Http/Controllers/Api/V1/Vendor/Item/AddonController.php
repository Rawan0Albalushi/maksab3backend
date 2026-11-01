<?php

namespace App\Http\Controllers\Api\V1\Vendor\Item;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Item\AddonAddRequest;
use App\Http\Requests\Vendor\Item\AddonIdRequest;
use App\Http\Requests\Vendor\Item\AddonStatusRequest;
use App\Http\Requests\Vendor\Item\AddonUpdateRequest;
use App\Http\Resources\Vendor\Item\AddonResource;
use App\Services\Item\AddonService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddonController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly AddonService $addonService) {}

    public function index(Request $request): JsonResponse
    {
        $addons = $this->addonService->getList(
            filters: $this->storeFilters($request),
            paginate: $this->pageParams($request),
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => AddonResource::collection($addons),
            'pagination' => $this->paginateFormatter($addons),
        ]);
    }

    public function store(AddonAddRequest $request): JsonResponse
    {
        if ($denied = $this->itemSectionDenial($request)) {
            return $denied;
        }

        $this->addonService->create($request->payload());

        return $this->responseFormatter(config('response.default_store_201'));
    }

    public function update(AddonUpdateRequest $request): JsonResponse
    {
        if ($denied = $this->itemSectionDenial($request)) {
            return $denied;
        }

        $addon = $this->addonService->find($request->input('id'), $this->storeFilters($request));

        if (! $addon) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $this->addonService->update($addon, $request->payload());

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function updateStatus(AddonStatusRequest $request): JsonResponse
    {
        if ($denied = $this->itemSectionDenial($request)) {
            return $denied;
        }

        $addon = $this->addonService->find($request->input('id'), $this->storeFilters($request));

        if (! $addon) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $this->addonService->updateStatus($addon, $request->input('status'));

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function destroy(AddonIdRequest $request): JsonResponse
    {
        if ($denied = $this->itemSectionDenial($request)) {
            return $denied;
        }

        $addon = $this->addonService->find($request->input('id'), $this->storeFilters($request));

        if (! $addon) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $this->addonService->delete($addon);

        return $this->responseFormatter(config('response.default_delete_200'));
    }

    private function storeFilters(Request $request): array
    {
        return ['store_id' => $this->vendorStoreId($request)];
    }

    private function itemSectionDenial(Request $request): ?JsonResponse
    {
        if ($this->vendorStore($request)?->item_section) {
            return null;
        }

        return $this->responseFormatter(
            config('response.forbidden_403'),
            errors: [['code' => 'unauthorized', 'message' => translate('messages.Permission denied')]]
        );
    }
}
