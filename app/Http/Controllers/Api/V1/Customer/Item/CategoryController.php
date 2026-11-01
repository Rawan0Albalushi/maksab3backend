<?php

namespace App\Http\Controllers\Api\V1\Customer\Item;

use App\Traits\Api\CachesApiPayloadTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Item\CategoryIdsListRequest;
use App\Http\Resources\Common\Item\ItemResource;
use App\Http\Resources\Customer\Item\CategoryResource;
use App\Http\Resources\Customer\Store\StoreResource;
use App\Services\Item\CategoryService;
use App\Traits\Api\ApiRequestContextTrait;
use App\Traits\Api\ModuleDelegationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Service\Services\ServiceCategoryService;

class CategoryController extends BaseApiController
{
    use CachesApiPayloadTrait;

    use ApiRequestContextTrait;
    use ModuleDelegationTrait;

    public function __construct(
        private readonly CategoryService $categoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->cachedJson('api.categories', $request, ['search' => $request->query('search'), 'featured' => $request->query('featured')], function () use ($request) {
            if (service_api_module_active()) {
                return $this->serviceResult(app(ServiceCategoryService::class)->apiCategories($this->serviceFilters($request)));
            }

            $categories = $this->categoryService->getList(
                filters: $this->filters($request) + [
                    'search' => $request->query('search'),
                    'featured' => $request->query('featured'),
                ],
                paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
            );

            return $this->paginatedResponse(CategoryResource::collection($categories), $categories);
        });
    }

    public function childes(Request $request, mixed $categoryId): JsonResponse
    {
        if (service_api_module_active()) {
            $serviceFilters = $this->serviceFilters($request);

            return $this->serviceResult(app(ServiceCategoryService::class)->apiChildes($categoryId, $serviceFilters['zone_ids'] ?? [], $serviceFilters['module_id'] ?? null));
        }

        $filters = $this->filters($request);

        $categories = $this->categoryService->getChildes(
            $categoryId,
            ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
            $filters['zone_ids'] ?? [],
            $filters['module_id'] ?? null,
        );

        return $this->paginatedResponse(CategoryResource::collection($categories), $categories);
    }

    public function popular(Request $request): JsonResponse
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceCategoryService::class)->apiPopularCategories($this->serviceFilters($request)['module_id']));
        }

        $categories = $this->categoryService->getPopularList(
            $this->filters($request),
            ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->paginatedResponse(CategoryResource::collection($categories), $categories);
    }

    public function top(Request $request): JsonResponse
    {
        $this->resolveCurrentModule($request);

        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceCategoryService::class)->apiTopCategories($this->serviceFilters($request, ['limit' => (int) $request->query('limit', 20), 'offset' => (int) $request->query('offset', 1)])));
        }

        $categories = $this->categoryService->getTopList(
            filters: $this->filters($request),
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->paginatedResponse(CategoryResource::collection($categories), $categories);
    }

    public function items(Request $request, mixed $categoryId): JsonResponse
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceCategoryService::class)->apiServices($categoryId, $this->serviceFilters($request)));
        }

        $this->applyZoneIds($request);

        $filters = $this->apiContext($request);
        $data = $this->categoryService->getCategoryItems(
            $categoryId, $filters['zone_header'], $this->perPage($request), $this->page($request),
            $filters['type'], $filters['customer_id']
        );

        return $this->itemsResponse($data);
    }

    public function itemsByCategories(CategoryIdsListRequest $request): JsonResponse
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceCategoryService::class)->apiCategoryServices($this->serviceFilters($request)));
        }

        $this->applyZoneIds($request);

        $filters = $request->filters();
        $data = $this->categoryService->getItemsForCategories(
            $filters['category_ids'], $filters['zone_header'], $request->perPage(), $request->page(),
            $filters['type'], null, false, false, null, null, $filters['customer_id']
        );

        return $this->itemsResponse($data);
    }

    public function allItems(Request $request, mixed $categoryId): JsonResponse
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceCategoryService::class)->apiAllServices($categoryId, $this->serviceFilters($request)));
        }

        $this->applyZoneIds($request);

        $filters = $this->filters($request);
        $items = $this->categoryService->loadItemRelations(
            $this->categoryService->getAllCategoryItems($categoryId, $filters['zone_header'])
        );

        return $this->responseFormatter(config('response.default_200'), ItemResource::collection($items));
    }

    public function featuredItems(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        return $this->cachedJson('api.categories_featured_items', $request, ['type' => $request->query('type', 'all')], function () use ($request) {
            if (service_api_module_active()) {
                return $this->serviceResult(app(ServiceCategoryService::class)->apiFeaturedServices($this->serviceFilters($request)));
            }

            $filters = $this->apiContext($request);
            $data = $this->categoryService->getFeaturedItems(
                $filters['zone_header'], $this->perPage($request), $this->page($request),
                $filters['type'], $filters['customer_id']
            );

            return $this->itemsResponse($data, ['categories' => $data['categories'] ?? null]);
        });
    }

    public function stores(Request $request, mixed $categoryId): JsonResponse
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceCategoryService::class)->apiProviders($categoryId, $this->serviceFilters($request)));
        }

        $this->applyZoneIds($request);

        $filters = $this->apiContext($request);
        $data = $this->categoryService->getCategoryStores(
            $categoryId, $filters['zone_header'], $this->perPage($request), $this->page($request),
            $filters['type'], $filters['longitude'], $filters['latitude']
        );

        return $this->storesResponse($data);
    }

    public function storesByCategories(CategoryIdsListRequest $request): JsonResponse
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceCategoryService::class)->apiCategoryProviders($this->serviceFilters($request)));
        }

        $this->applyZoneIds($request);

        $filters = $request->filters();
        $data = $this->categoryService->getStoresForCategories(
            $filters['category_ids'], $filters['zone_header'], $request->perPage(), $request->page(),
            $filters['type'], $filters['longitude'], $filters['latitude'],
            null, null, null, $filters['customer_id']
        );

        return $this->storesResponse($data);
    }

    private function itemsResponse(array $data, array $extra = []): JsonResponse
    {
        $content = array_filter([
            'data' => ItemResource::collection($this->categoryService->loadItemRelations($data['products'] ?? [])),
            'pagination' => $this->paginateFormatter($data['paginator']),
            'categories' => $extra['categories'] ?? null,
        ], fn ($value) => $value !== null);

        return $this->responseFormatter(config('response.default_200'), $content);
    }

    private function storesResponse(array $data): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), [
            'data' => StoreResource::collection($this->categoryService->loadStoreRelations(collect($data['stores'] ?? []))),
            'pagination' => $this->paginateFormatter($data['paginator']),
        ]);
    }

    private function paginatedResponse($resource, $paginator): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), [
            'data' => $resource,
            'pagination' => $this->paginateFormatter($paginator),
        ]);
    }

    private function resolveCurrentModule(Request $request): void
    {
        if (! config('module.current_module_data') && $request->hasHeader('moduleId')) {
            $module = getModule($request->header('moduleId'));

            if ($module) {
                config(['module.current_module_data' => $module]);
            }
        }
    }

    private function filters(Request $request): array
    {
        return $this->apiContext($request);
    }
}
