<?php

namespace App\Http\Controllers\Api\V1\Vendor\Item;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Item\ItemResource;
use App\Http\Resources\Vendor\Item\CategoryResource;
use App\Services\Item\CategoryService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly CategoryService $categoryService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $categories = $this->categoryService->getStoreCategories(
            $this->filters($request),
            ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => CategoryResource::collection($categories),
            'pagination' => $this->paginateFormatter($categories),
        ]);
    }

    public function childes(Request $request, mixed $categoryId): JsonResponse
    {
        $categories = $this->categoryService->getStoreChildes(
            $categoryId,
            $this->filters($request),
            ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => CategoryResource::collection($categories),
            'pagination' => $this->paginateFormatter($categories),
        ]);
    }

    public function items(Request $request, mixed $categoryId): JsonResponse
    {
        $items = $this->categoryService->getStoreItems(
            filters: $this->filters($request) + [
                'category' => $categoryId,
                'sub_category' => $request->input('sub_category') == 1,
            ],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemResource::collection($items),
            'pagination' => $this->paginateFormatter($items),
        ]);
    }

    private function filters(Request $request): array
    {
        $store = $this->vendorStore($request);

        return [
            'store_id' => $store?->id,
            'module_id' => $this->currentModuleId(),
            'is_service' => ($store?->module_type ?? null) === 'service' && service_addon_active(),
        ];
    }
}
