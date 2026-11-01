<?php

namespace App\Http\Controllers\Api\V1\Vendor\Item;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Vendor\Item\PendingItemResource;
use App\Http\Resources\Common\Item\TempProductResource;
use App\Services\Item\ItemService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendingItemController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly ItemService $itemService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->itemService->getPendingList([
                'store_id' => $this->vendorStoreId($request),
                'name' => $request->input('name'),
                'category_id' => $request->input('category_id', 'all'),
                'sub_category_id' => $request->input('sub_category_id', 'all'),
                'store_category_id' => $request->input('store_category_id'),
                'status' => $request->input('status', 'all'),
                'type' => $request->input('type', 'all'),
            ], $this->pageParams($request)),
            PendingItemResource::class
        );
    }

    public function show(Request $request, mixed $id): JsonResponse
    {
        $product = $this->itemService->findPendingForStore($id, $this->vendorStoreId($request));

        if (! $product) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'id');
        }

        return $this->responseFormatter(config('response.default_200'), new TempProductResource($product));
    }
}
