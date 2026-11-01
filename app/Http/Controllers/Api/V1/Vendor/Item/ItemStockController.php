<?php

namespace App\Http\Controllers\Api\V1\Vendor\Item;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Item\ItemStockUpdateRequest;
use App\Http\Resources\Common\Item\ProductListResource;
use App\Services\Item\ItemService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemStockController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly ItemService $itemService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->itemService->getLowStockList([
                'store_id' => $this->vendorStoreId($request),
                'category_id' => $request->query('category_id', 'all'),
                'type' => $request->query('type', 'all'),
            ], $this->pageParams($request)),
            ProductListResource::class
        );
    }

    public function update(ItemStockUpdateRequest $request): JsonResponse
    {
        return $this->serviceResponse(
            $this->itemService->updateStockForStore($request->payload(), $this->vendorStore($request)),
            config('response.default_update_200')
        );
    }
}
