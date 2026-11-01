<?php

namespace App\Http\Controllers\Api\V1\Customer\Search;

use App\Services\Customer\PersonalizationService;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Search\CombinedSearchRequest;
use App\Http\Resources\Common\Item\ItemListResource;
use App\Services\Item\CategoryService;
use App\Services\Item\ItemService;
use App\Services\Search\SearchLogService;
use App\Services\Store\StoreService;
use App\Traits\Api\ItemListFiltersTrait;
use App\Traits\Api\StoreListFiltersTrait;
use Illuminate\Http\Request;
use App\Traits\Api\ApiRequestContextTrait;

class SearchController extends BaseApiController
{
    use ApiRequestContextTrait;
    use ItemListFiltersTrait;
    use StoreListFiltersTrait;

    public function __construct(
        protected SearchLogService $searchLogService,
        protected ItemService $itemService,
        protected StoreService $storeService,
        protected CategoryService $categoryService
    ) {}

    public function trending(Request $request)
    {
        $this->applyZoneIds($request);
        $isGlobal = $request->boolean('is_global');

        if (! $isGlobal && ! config('module.current_module_data') && $request->hasHeader('moduleId')) {
            config(['module.current_module_data' => getModule($request->header('moduleId'))]);
        }

        if (! $isGlobal && ! is_numeric($this->currentModuleId())) {
            return $this->responseFormatter(config('response.default_200'), ['trending_searches' => []]);
        }

        return $this->responseFormatter(config('response.default_200'), [
            'trending_searches' => $this->searchLogService->getTrendingRows(
                $isGlobal ? null : (int) $this->currentModuleId(),
                (string) $request->header('zoneId'),
                filter_var($request->query('cache', true), FILTER_VALIDATE_BOOLEAN)
            ),
        ]);
    }

    public function combinedData(CombinedSearchRequest $request)
    {
        $this->applyZoneIds($request);

        $items = $this->itemPayload($request);
        $stores = $this->storePayload($request);
        $payload = $request->query('list_type') === 'item' ? $items : $stores;

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $payload['data'],
            'categories' => $payload['categories'],
            'total_count_item' => $items['total_size'],
            'total_count_store' => $stores['total_size'],
            'pagination' => $this->paginateFormatter($payload['paginator']),
        ]);
    }

    private function itemPayload(CombinedSearchRequest $request): array
    {
        $filters = $this->itemSearchFilters($request);
        $filters['category_ids'] = self::categoryIdArray($request->input('category_ids'));
        $filters['min'] = $filters['min'] == 0 ? 0.0001 : $filters['min'];
        $paginate = $this->pageParams($request);

        $result = match ($request->query('data_type', 'all')) {
            'searched' => $this->searchedItems($request, $filters, $paginate),
            'discounted' => $this->itemService->getDiscountedList($filters, $paginate),
            'brand' => $this->itemService->getBrandList($filters, $paginate),
            'new' => $this->itemService->getNewArrivalList($filters, $paginate),
            'category' => $this->categoryService->getItemsForCategories(
                $filters['category_ids'], $filters['zone_id'], $this->perPage($request), $this->page($request),
                $filters['type'], $filters['filter'], $filters['min'], $filters['max'],
                $filters['rating_count'], $filters['brand_ids'], $filters['user_id']
            ),
            default => $this->itemService->getCombinedList($filters, $paginate),
        };

        return [
            'data' => ItemListResource::collection(
                $this->itemService->loadListRelations($result['paginator']->items())
            )->toArray($request),
            'categories' => $result['categories'],
            'total_size' => $result['total_size'],
            'paginator' => $result['paginator'],
        ];
    }

    private function searchedItems(CombinedSearchRequest $request, array $filters, array $paginate): array
    {
        if ($filters['user_id']) {
            app(PersonalizationService::class)->recordSearchAction(
                $filters['user_id'],
                $request->input('name'),
                $request->header('moduleId') ? (int) $request->header('moduleId') : null
            );
        }

        $result = $this->itemService->getSearchList($filters, $paginate);

        $this->searchLogService->log(
            keyword: (string) $request->input('name'),
            userId: $filters['user_id'],
            guestId: $filters['user_id'] ? null : ($request->input('guest_id') ?? $request->header('guestId')),
            moduleId: (int) ($this->currentModuleId() ?? 0),
            zoneId: (string) $request->header('zoneId'),
            resultCount: (int) $result['paginator']->total(),
        );

        return $result;
    }

    private function storePayload(CombinedSearchRequest $request): array
    {
        $filters = $this->storeListFilters($request);
        $filters['store_filter'] = $this->storeFilterInputs($request, ['quick_action', 'type', 'sort_by', 'rating', 'price_min', 'price_max']);
        $paginate = $this->pageParams($request);

        $result = match ($request->query('data_type', 'all')) {
            'searched' => $this->storeService->searchList($filters, $paginate),
            'discounted' => $this->storeService->getDiscountedList($filters, $paginate),
            'category' => $this->categoryService->getStoresForCategories(
                $request->input('category_ids'), $filters['zone_id'], $this->perPage($request), $this->page($request),
                $filters['type'], $filters['longitude'], $filters['latitude'], $filters['filter'],
                $filters['rating_count'], $filters['store_filter'], $filters['user_id']
            ),
            default => $this->storeService->getList($filters, $paginate),
        };

        return [
            'data' => $this->storeService->buildRowsWithTopItems($result['paginator']->items(), $filters['zone_id']),
            'categories' => [],
            'total_size' => $result['total_size'],
            'paginator' => $result['paginator'],
        ];
    }
}
