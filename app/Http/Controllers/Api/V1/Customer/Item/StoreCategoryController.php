<?php

namespace App\Http\Controllers\Api\V1\Customer\Item;

use App\CentralLogics\Helpers;
use App\Traits\Api\ModuleDelegationTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Item\StoreCategoryItemsRequest;
use App\Http\Resources\Customer\Item\CategorySummaryResource;
use App\Http\Resources\Customer\Item\StoreCategoryItemResource;
use App\Http\Resources\Customer\Item\StoreCategoryResource;
use App\Services\Store\StoreCategoryService;
use App\Traits\Api\ApiRequestContextTrait;
use App\Traits\Api\StoreItemFiltersTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\Cache\ApiCache;

class StoreCategoryController extends BaseApiController
{
    use ModuleDelegationTrait;

    use ApiRequestContextTrait;
    use StoreItemFiltersTrait;

    public function __construct(
        private readonly StoreCategoryService $storeCategoryService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if (! Helpers::storeCategoryStatus()) {
            return $this->responseFormatter(config('response.default_200'), ['data' => [], 'pagination' => null]);
        }

        $categories = $this->storeCategoryService->getActiveList(
            filters: [
                'module_id' => $this->currentModuleId(),
                'store_id' => $request->query('store_id'),
                'name' => $request->query('name'),
            ],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->listResponse($categories);
    }

    public function byStore(Request $request, mixed $storeId): JsonResponse
    {
        if (! Helpers::storeCategoryStatus()) {
            return $this->responseFormatter(config('response.default_200'), ['data' => [], 'pagination' => null]);
        }

        $categories = $this->storeCategoryService->getActiveList(
            filters: ['store_id' => $storeId],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->listResponse($categories);
    }

    public function items(StoreCategoryItemsRequest $request): JsonResponse
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(\Modules\Service\Services\ServiceProviderService::class)->apiCategoriesWithServices($this->serviceFilters($request, ['rating_params' => $request->only(['rating','rating_plus','rating_count','rating_1','rating_2','rating_3','rating_4','rating_5','rating_1_plus','rating_2_plus','rating_3_plus','rating_4_plus']), 'sort_by' => $request->query('sort_by'), 'filter_by' => $request->query('filter_by'), 'rating_count' => $request->query('rating_count')])));
        }

        $this->applyZoneIds($request);

        $filters = $this->itemFilters($request);
        $paginate = ['per_page' => $this->perPage($request), 'page' => $this->page($request)];

        $payload = ApiCache::remember(
            'store_cat_items',
            $filters + $paginate + ['locale' => app()->getLocale()],
            fn () => $this->itemsPayload($filters, $paginate)
        );

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    private function itemsPayload(array $filters, array $paginate): array
    {
        $data = $this->storeCategoryService->getCategoriesWithItems($filters, $paginate);
        $grouped = [];

        foreach ($data['grouped'] as $categoryId => $items) {
            $grouped[$categoryId] = StoreCategoryItemResource::collection($items)->resolve();
        }

        return [
            'total_size' => $data['total_size'],
            'limit' => $data['paginator']?->perPage() ?? $paginate['per_page'],
            'offset' => $data['paginator']?->currentPage() ?? $paginate['page'],
            'category_source' => $data['category_source'],
            'categories' => $data['categories']
                ->map(fn ($category) => (new CategorySummaryResource($category, (int) ($data['counts'][$category->id] ?? 0)))->resolve())
                ->values()
                ->all(),
            'category_wise_items' => $grouped ? (object) $grouped : (object) [],
        ];
    }

    private function listResponse(mixed $categories): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), [
            'data' => StoreCategoryResource::collection($categories),
            'pagination' => $this->paginateFormatter($categories),
        ]);
    }

    private function itemFilters(StoreCategoryItemsRequest $request): array
    {
        return $this->storeItemFilters($request, (int) $request->query('store_id'));
    }
}
