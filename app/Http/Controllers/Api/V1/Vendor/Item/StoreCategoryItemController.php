<?php

namespace App\Http\Controllers\Api\V1\Vendor\Item;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Item\AssignItemsRequest;
use App\Http\Resources\Common\Item\ItemResource;
use App\Http\Resources\Vendor\Item\AssignableItemResource;
use App\Services\Item\ItemService;
use App\Services\Store\StoreCategoryService;
use App\Traits\Api\ApiRequestContextTrait;
use App\Traits\Store\VendorStoreCategoryTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreCategoryItemController extends BaseApiController
{
    use ApiRequestContextTrait;
    use VendorStoreCategoryTrait;

    public function __construct(
        private readonly ItemService $itemService,
        private readonly StoreCategoryService $storeCategoryService
    ) {
    }

    public function index(Request $request, mixed $id): JsonResponse
    {
        if ($blocked = $this->guardStoreCategory($request)) {
            return $blocked;
        }

        $items = $this->itemService->getStoreCategoryList(
            filters: ['store_category_id' => $id, 'store_id' => $this->vendorStoreId($request)],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemResource::collection($items),
            'pagination' => $this->paginateFormatter($items),
        ]);
    }

    public function assignable(Request $request, mixed $id): JsonResponse
    {
        if ($blocked = $this->guardStoreCategory($request)) {
            return $blocked;
        }

        $storeId = $this->vendorStoreId($request);
        $category = $this->storeCategoryService->findOwned($id, $storeId);

        if (! $category) {
            return $this->storeCategoryNotFound();
        }

        $filters = [
            'store_id' => $storeId,
            'store_category_id' => (int) $category->id,
            'model' => $this->bindableModel($request),
            'search' => $request->query('search'),
        ];

        $items = $this->itemService->getAssignableList(
            $filters,
            ['per_page' => $this->perPage($request), 'page' => $this->page($request)]
        );

        $isService = $this->isServiceModule($request);
        $moduleType = $this->vendorModuleType($request);

        return $this->responseFormatter(config('response.default_200'), [
            'category' => ['id' => (int) $category->id, 'name' => $category->name],
            'unassigned_count' => $this->itemService->unassignedCount($filters),
            'data' => collect($items->items())
                ->map(fn ($item) => (new AssignableItemResource($item, (int) $category->id, $moduleType, $isService))->resolve())
                ->values()
                ->all(),
            'pagination' => $this->paginateFormatter($items),
        ]);
    }

    public function assign(AssignItemsRequest $request): JsonResponse
    {
        if ($blocked = $this->guardStoreCategory($request)) {
            return $blocked;
        }

        $storeId = $this->vendorStoreId($request);
        $category = $this->storeCategoryService->findOwned($request->input('category_id'), $storeId);

        if (! $category) {
            return $this->storeCategoryNotFound();
        }

        $assigned = $this->itemService->syncStoreCategoryAssignment([
            'store_id' => $storeId,
            'store_category_id' => (int) $category->id,
            'model' => $this->bindableModel($request),
        ], $request->itemIds());

        return $this->responseFormatter(config('response.default_update_200'), [
            'message' => $this->isServiceModule($request)
                ? translate('messages.Services assigned successfully')
                : translate('messages.Items assigned successfully'),
            'assigned_count' => $assigned,
        ]);
    }
}
