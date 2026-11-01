<?php

namespace App\Http\Controllers\Api\V1\Vendor\Item;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Item\ItemIdRequest;
use App\Http\Requests\Vendor\Item\ItemOrganicRequest;
use App\Http\Requests\Vendor\Item\ItemSearchRequest;
use App\Http\Requests\Vendor\Item\ItemStatusRequest;
use App\Http\Requests\Vendor\Item\ItemStoreRequest;
use App\Http\Requests\Vendor\Item\ItemUpdateRequest;
use App\Http\Resources\Common\Item\ProductDetailResource;
use App\Http\Resources\Common\Item\ProductListResource;
use App\Services\Item\ItemService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly ItemService $itemService) {}

    public function store(ItemStoreRequest $request): JsonResponse
    {
        return $this->serviceResponse(
            $this->itemService->createForStore($request->payload(), $this->vendorStore($request))
        );
    }

    public function update(ItemUpdateRequest $request): JsonResponse
    {
        return $this->serviceResponse(
            $this->itemService->updateForStore($request->payload(), $this->vendorStore($request))
        );
    }

    public function destroy(ItemIdRequest $request): JsonResponse
    {
        return $this->serviceResponse(
            $this->itemService->deleteForStore($request->payload(), $this->vendorStore($request)),
            config('response.default_delete_200')
        );
    }

    public function show(Request $request, mixed $id): JsonResponse
    {
        $item = $this->itemService->findForVendorDetail($id, $this->vendorStoreId($request));

        if (! $item) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'product-001');
        }

        return $this->responseFormatter(config('response.default_200'), new ProductDetailResource($item));
    }

    public function updateStatus(ItemStatusRequest $request): JsonResponse
    {
        return $this->serviceResponse(
            $this->itemService->updateStatusForStore($request->validated(), $this->vendorStore($request)),
            config('response.default_update_200')
        );
    }

    public function updateOrganic(ItemOrganicRequest $request): JsonResponse
    {
        return $this->serviceResponse(
            $this->itemService->updateOrganicForStore($request->validated(), $this->vendorStore($request)),
            config('response.default_update_200')
        );
    }

    public function updateRecommended(ItemStatusRequest $request): JsonResponse
    {
        return $this->serviceResponse(
            $this->itemService->updateRecommendedForStore($request->validated(), $this->vendorStore($request)),
            config('response.default_update_200')
        );
    }

    public function index(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->itemService->getVendorApprovedList([
                'store_id' => $this->vendorStoreId($request),
                'search' => $request->input('search'),
                'category_id' => $request->input('category_id', 0),
                'type' => $request->query('type', 'all'),
            ], $this->pageParams($request)),
            ProductListResource::class
        );
    }

    public function search(ItemSearchRequest $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->itemService->getVendorSearchList([
                'store_id' => $this->vendorStoreId($request),
                'name' => $request->input('name'),
                'category_id' => $request->input('category_id'),
                'store_category_id' => $request->input('store_category_id'),
                'requested_store_id' => $request->input('store_id'),
            ], $this->pageParams($request)),
            ProductListResource::class
        );
    }
}
