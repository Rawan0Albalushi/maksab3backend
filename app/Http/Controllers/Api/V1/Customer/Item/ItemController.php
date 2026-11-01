<?php

namespace App\Http\Controllers\Api\V1\Customer\Item;

use App\Services\Item\CategoryService;
use App\Traits\Api\ModuleDelegationTrait;
use App\Http\Resources\Common\Item\ItemDetailResource;
use App\Http\Resources\Common\Store\StoreDetailResource;
use App\Http\Resources\Common\Item\ItemOfferResource;
use App\Http\Resources\Common\Item\ItemListResource;
use App\Http\Requests\Customer\Item\ItemCategoryIdsRequest;
use App\Http\Requests\Customer\Item\ItemLatestListRequest;
use App\Http\Requests\Customer\Item\ItemSearchRequest;
use App\Http\Requests\Customer\Item\ItemStoreScopedRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use App\CentralLogics\Helpers;
use App\Services\Customer\PersonalizationService;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\Item\ItemService;
use App\Services\Search\SearchLogService;
use App\Services\Store\StoreService;
use App\Traits\Item\ItemFilterTrait;
use App\Traits\Api\ItemListFiltersTrait;
use App\Services\Search\RecentSearchService;
use App\Traits\Api\ApiRequestContextTrait;
use App\Traits\Api\CachesApiPayloadTrait;

class ItemController extends BaseApiController
{
    use ApiRequestContextTrait;
    use CachesApiPayloadTrait;
    use ModuleDelegationTrait;

    use ItemListFiltersTrait;

    use ItemFilterTrait;

    public function __construct(
        protected SearchLogService $searchLogService,
        protected ItemService $itemService,
        protected CategoryService $categoryService,
        protected StoreService $storeService
    ) {}

    public function latest(ItemLatestListRequest $request)
    {

        $this->applyZoneIds($request);

        $items = $this->itemService->getLatestList(
            filters: $this->itemListFilters($request),
            paginate: $this->pageParams($request),
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemListResource::collection($this->itemService->loadListRelations($items['products']))->toArray($request),
            'pagination' => $this->paginateFormatter($items['paginator']),
        ]);
    }

    public function newArrivals(Request $request)
    {
        $this->applyZoneIds($request);

        $items = $this->itemService->getNewArrivalList(
            filters: $this->itemListFilters($request),
            paginate: $this->pageParams($request),
        );
        $items['categories'] = $items['categories'];

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemListResource::collection($this->itemService->loadListRelations($items['products']))->toArray($request),
            'pagination' => $this->paginateFormatter($items['paginator']),
        ]);
    }

    public function search(ItemSearchRequest $request)
    {
        $this->applyZoneIds($request);

        $userId = $this->itemListFilters($request)['user_id'];

        if ($userId) {
            app(RecentSearchService::class)->record([
                'user_id' => $userId,
                'keyword' => $request->input('name'),
                'route_uri' => $request->path(),
                'route_full_url' => $request->fullUrl(),
            ]);
            app(PersonalizationService::class)->recordSearchAction($userId, $request->input('name'), $request->header('moduleId') ? (int) $request->header('moduleId') : null);
        }

        $result = $this->itemService->getSearchList(
            filters: $request->searchFilters(),
            paginate: $this->pageParams($request),
        );

        $this->searchLogService->log(
            keyword: (string) $request->input('name'),
            userId: $userId,
            guestId: $userId ? null : ($request->input('guest_id') ?? $request->header('guestId')),
            moduleId: (int) (config('module.current_module_data')['id'] ?? 0),
            zoneId: (string) $request->header('zoneId'),
            resultCount: (int) $result['paginator']->total(),
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemListResource::collection($this->itemService->loadListRelations($result['paginator']->items()))->toArray($request),
            'categories' => $result['categories'],
            'pagination' => $this->paginateFormatter($result['paginator']),
        ]);
    }

    public function searchSuggestions(ItemSearchRequest $request)
    {
        $this->applyZoneIds($request);

        $items = $this->itemService->getSuggestionList(
            filters: [
                'zone_id' => $request->header('zoneId'),
                'name' => $request->input('name'),
                'type' => $request->query('type', 'all'),
                'category_id' => $request->input('category_id'),
                'store_category_id' => $request->input('store_category_id'),
                'store_id' => $request->input('store_id'),
            ],
            paginate: $this->pageParams($request),
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $items->items(),
            'pagination' => $this->paginateFormatter($items),
        ]);
    }

    public function popular(Request $request)
    {
        $this->applyZoneIds($request);


        $payload = $this->cachedPayload('api.items_popular', $request, $this->itemListFilters($request), function () use ($request) {
            $items = $this->itemService->getPopularList(
                filters: $this->itemListFilters($request),
                paginate: $this->pageParams($request),
            );

            return [
                'data' => ItemListResource::collection($this->itemService->loadListRelations($items['products']))->toArray($request),
                'pagination' => $this->paginateFormatter($items['paginator']),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function mostReviewed(Request $request)
    {
        $this->applyZoneIds($request);

        $payload = $this->cachedPayload('api.items_most_reviewed', $request, $this->itemListFilters($request), function () use ($request) {
            $items = $this->itemService->getMostReviewedList(
                filters: $this->itemListFilters($request),
                paginate: $this->pageParams($request),
            );

            return [
                'data' => ItemListResource::collection($this->itemService->loadListRelations($items['products']))->toArray($request),
                'pagination' => $this->paginateFormatter($items['paginator']),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function topRated(Request $request)
    {
        $this->applyZoneIds($request);

        $items = $this->itemService->getTopRatedList(
            filters: $this->itemListFilters($request),
            paginate: $this->pageParams($request),
        );
        $items['categories'] = $items['categories'];


        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemListResource::collection($this->itemService->loadListRelations($items['products']))->toArray($request),
            'pagination' => $this->paginateFormatter($items['paginator']),
        ]);
    }

    public function discounted(Request $request)
    {
        $this->applyZoneIds($request);


        $payload = $this->cachedPayload('api.items_discounted', $request, $this->itemListFilters($request), function () use ($request) {
            $items = $this->itemService->getDiscountedList(
                filters: $this->itemListFilters($request),
                paginate: $this->pageParams($request),
            );

            return [
                'data' => ItemListResource::collection($this->itemService->loadListRelations($items['products']))->toArray($request),
                'pagination' => $this->paginateFormatter($items['paginator']),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function suggested(ItemStoreScopedRequest $request)
    {

        $this->applyZoneIds($request);
        $items = $this->itemService->getCartSuggestionList(
            filters: $this->itemListFilters($request),
            paginate: $this->pageParams($request),
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemListResource::collection($this->itemService->loadListRelations($items['products']))->toArray($request),
            'pagination' => $this->paginateFormatter($items['paginator']),
        ]);
    }

    public function show(Request $request, $id)
    {
        try {
            $item = $this->itemService->findDetail($id, (int) ($request['campaign'] ?? 0) === 1);

            if (! $item) {
                return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'product-001', 'message' => translate('messages.Item currently unavailable')],
            ]);
            }
            if ($item && auth('api')->check()) {
                Helpers::visitor_log(
                    model: 'item',
                    user_id: auth('api')->id(),
                    visitor_log_id: $item->id,
                    order_count: false
                );
                app(PersonalizationService::class)->recordItemAction(auth('api')->id(), $item->id, 'item_view');
            }

            $store = $this->storeService->findDetail($item->store_id);
            if($store)
            {
                $categoryIds = $this->itemService->storeCategoryIds($item->store_id);
                $store = (new StoreDetailResource($this->storeService->loadStoreRelations($store), [
                    'category_ids' => $categoryIds,
                    'category_details' => $this->itemService->categoriesByIds($categoryIds),
                    'price_range' => $this->itemService->priceRange($item->store_id),
                ]))->toArray($request);
            }
            $item = ItemDetailResource::legacyCollection($this->itemService->loadDetailRelations(collect([$item])))[0];
            $item['store_details'] = $store;
            return $this->responseFormatter(config('response.default_200'), $item);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'product-001', 'message' => translate('No data found')],
            ]);
        }
    }

    public function related(Request $request,$id)
    {
        $this->applyZoneIds($request);
        if ($this->itemService->exists($id)) {
            $items = $this->itemService->getRelatedList(
                filters: array_merge($this->itemListFilters($request), ['product_id' => $id]),
                paginate: $this->pageParams($request),
            );

            return $this->responseFormatter(config('response.default_200'), [
                'data' => ItemListResource::collection($this->itemService->loadListRelations($items->items()))->toArray($request),
                'pagination' => $this->paginateFormatter($items),
            ]);
        }
        return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'product-001', 'message' => translate('No data found')],
            ]);
    }
    public function relatedStoreItems(Request $request,$id)
    {
        $this->applyZoneIds($request);
        if ($this->itemService->exists($id)) {
            $items = $this->itemService->getRelatedStoreList(
                filters: array_merge($this->itemListFilters($request), ['product_id' => $id]),
                paginate: $this->pageParams($request),
            );

            return $this->responseFormatter(config('response.default_200'), [
                'data' => ItemListResource::collection($this->itemService->loadListRelations($items->items()))->toArray($request),
                'pagination' => $this->paginateFormatter($items),
            ]);
        }
        return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'product-001', 'message' => translate('No data found')],
            ]);
    }

    public function recommended(Request $request)
    {
        $this->applyZoneIds($request);

        $payload = $this->cachedPayload('api.items_recommended', $request, $this->itemListFilters($request), function () use ($request) {
            $items = $this->itemService->getRecommendedList(
                filters: $this->itemListFilters($request),
                paginate: $this->pageParams($request),
            );

            return [
                'data' => ItemListResource::collection($this->itemService->loadListRelations($items['products']))->toArray($request),
                'pagination' => $this->paginateFormatter($items['paginator']),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function setMenus(Request $request)
    {
        if (! $this->itemService->isSetMenuSupported()) {
            return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'product-001', 'message' => 'Set menu not found!'],
            ]);
        }

        $items = $this->itemService->getSetMenuList(
            paginate: $this->pageParams($request),
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemListResource::collection($this->itemService->loadListRelations($items->items()))->toArray($request),
            'pagination' => $this->paginateFormatter($items),
        ]);
    }

    public function itemOrStoreSearch(ItemSearchRequest $request)
    {
        $this->applyZoneIds($request);

        if (! $request->hasHeader('longitude') || ! $request->hasHeader('latitude')) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'longitude-latitude', 'message' => translate('messages.Longitude-latitude required')],
            ]);
        }

        $isGlobal = $request->boolean('is_global');

        if (! $isGlobal && ! config('module.current_module_data') && $request->hasHeader('moduleId')) {
            $resolvedModule = getModule($request->header('moduleId'));
            if ($resolvedModule) {
                config(['module.current_module_data' => $resolvedModule]);
            }
        }

        $module = $isGlobal ? null : config('module.current_module_data');
        $filters = $request->suggestFilters($module);
        $paginate = ['per_page' => $this->perPage($request), 'page' => $this->page($request)];

        $items = (service_api_module_active() && ! $isGlobal)
            ? collect()
            : $this->itemService->searchSuggestList($filters, $paginate);

        $stores = $this->storeService->searchSuggestList($filters, $paginate);

        $itemRows = $items instanceof SupportCollection ? $items : collect($items->items());

        if (service_api_module_active() || ($isGlobal && addon_published_status('Service'))) {
            $itemRows = $itemRows->concat(app(\Modules\Service\Services\ServiceProviderService::class)->searchServices(
                $request->input('name'),
                json_decode((string) $request->header('zoneId'), true) ?? [],
                $isGlobal ? null : ($module['id'] ?? null),
                filters: $this->serviceFilters($request)
            ));
        }

        if (auth('api')->check()) {
            app(PersonalizationService::class)->recordSearchAction($this->itemListFilters($request)['user_id'], $request->input('name'), $module ? (int) $module['id'] : null);
        }

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $itemRows,
            'stores' => $stores->items(),
            'pagination' => $this->paginateFormatter($stores),
        ]);
    }

    public function commonConditions(ItemStoreScopedRequest $request)
    {
        $this->applyZoneIds($request);

        $items = $this->itemService->getStoreConditionList(
            filters: [
                'zone_id' => $request->header('zoneId'),
                'type' => $request->query('type', 'all'),
                'store_id' => $request->input('store_id'),
                'store_category_id' => $request->input('store_category_id'),
                'user_id' => $this->itemListFilters($request)['user_id'],
            ],
            paginate: $this->pageParams($request),
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemListResource::collection($this->itemService->loadListRelations($items->items()))->toArray($request),
            'pagination' => $this->paginateFormatter($items),
        ]);
    }

    public function basic(Request $request)
    {

        $this->applyZoneIds($request);

        $payload = $this->cachedPayload('api.items_basic', $request, $this->itemListFilters($request), function () use ($request) {
            $items = $this->itemService->getPopularBasicList(
                filters: $this->itemListFilters($request),
                paginate: $this->pageParams($request),
            );

            return [
                'data' => ItemListResource::collection($this->itemService->loadListRelations($items['products']))->toArray($request),
                'pagination' => $this->paginateFormatter($items['paginator']),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function organic(Request $request)
    {
        $this->applyZoneIds($request);


        $items = $this->itemService->getOrganicList(
            filters: $this->itemListFilters($request),
            paginate: $this->pageParams($request),
        );
        $items['categories'] = $items['categories'];


        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemListResource::collection($this->itemService->loadListRelations($items['products']))->toArray($request),
            'pagination' => $this->paginateFormatter($items['paginator']),
        ]);
    }

    public function index(ItemCategoryIdsRequest $request)
    {
        $this->applyZoneIds($request);

        $filters = $this->itemListFilters($request);
        $paginate = $this->pageParams($request);

        $items = match ($request->query('data_type', 'all')) {
            'searched' => $this->itemService->getSearchList(
                filters: $this->itemSearchFilters($request),
                paginate: $paginate,
            ),
            'discounted' => $this->itemService->getDiscountedList(filters: $filters, paginate: $paginate),
            'new' => $this->itemService->getNewArrivalList(filters: $filters, paginate: $paginate),
            'top_rated' => $this->itemService->getTopRatedList(filters: $filters, paginate: $paginate),
            'organic' => $this->itemService->getOrganicList(filters: $filters, paginate: $paginate),
            'category' => $this->categoryService->getItemsForCategories(
                $filters['category_ids'], $filters['zone_id'], $paginate['per_page'], $paginate['page'],
                $filters['type'], $filters['filter'], $filters['min'], $filters['max'], $filters['rating_count'], null, $filters['user_id']
            ),
            default => $this->itemService->getEmptyList($paginate),
        };

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemListResource::collection($this->itemService->loadListRelations($items['products']))->toArray($request),
            'pagination' => $this->paginateFormatter($items['paginator']),
        ]);
    }



    public function recentlyViewed(Request $request)
    {
        $this->applyZoneIds($request);

        $items = $this->itemService->getRecentlyViewedList(
            filters: $this->itemListFilters($request),
            paginate: $this->pageParams($request),
        );
        $items['categories'] = $items['categories'];


        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemListResource::collection($this->itemService->loadListRelations($items['products']))->toArray($request),
            'pagination' => $this->paginateFormatter($items['paginator']),
        ]);
    }

    public function recentOrdered(Request $request)
    {
        $this->applyZoneIds($request);

        $items = $this->itemService->getRecentOrderedList([
            'zone_id' => $request->header('zoneId'),
            'module_id' => getModuleId($request->header('moduleId')),
            'type' => $request->query('type', 'all'),
            'user_id' => auth('api')->id(),
        ], $this->pageParams($request));

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemListResource::collection($items)->toArray($request),
            'pagination' => $this->paginateFormatter($items),
        ]);
    }

    public function offerItems(Request $request)
    {
        $this->applyZoneIds($request);

        $items = $this->itemService->getOfferList(
            filters: $this->offerItemFilters($request),
            paginate: $this->pageParams($request),
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemOfferResource::collection($items->items()),
            'pagination' => $this->paginateFormatter($items),
        ]);
    }

    public function offerStores(Request $request)
    {
        if (! config('module.current_module_data') && $request->hasHeader('moduleId')) {
            $resolvedModule = getModule($request->header('moduleId'));
            if ($resolvedModule) {
                config(['module.current_module_data' => $resolvedModule]);
            }
        }

        if (service_api_module_active()) {
            return $this->serviceResult(app(\Modules\Service\Services\ServiceProviderService::class)->apiOffers($this->serviceFilters($request)));
        }

        $this->applyZoneIds($request);

        $stores = $this->storeService->getOfferList(
            filters: $this->offerStoreFilters($request),
            paginate: $this->pageParams($request),
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $stores->items(),
            'pagination' => $this->paginateFormatter($stores),
        ]);
    }
}
