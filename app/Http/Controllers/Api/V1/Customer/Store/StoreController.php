<?php

namespace App\Http\Controllers\Api\V1\Customer\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Store\StoreReviewListRequest;
use App\Http\Requests\Customer\Store\StoreSearchRequest;
use App\Http\Resources\Common\Item\ItemListResource;
use App\Http\Resources\Common\Store\StoreListResource;
use App\Http\Resources\Common\Store\StoreShowResource;
use App\Services\Customer\PersonalizationService;
use App\Services\Item\ItemService;
use App\Services\Item\ReviewService;
use App\Services\Store\StoreService;
use App\Traits\Api\ModuleDelegationTrait;
use App\Traits\Api\CachesApiPayloadTrait;
use App\Traits\Api\StoreItemFiltersTrait;
use App\Traits\Api\StoreListFiltersTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Modules\Service\Services\ServiceProviderService;
use App\Traits\Api\ApiRequestContextTrait;

class StoreController extends BaseApiController
{
    use ApiRequestContextTrait;
    use CachesApiPayloadTrait;
    use ModuleDelegationTrait;
    use StoreItemFiltersTrait;
    use StoreListFiltersTrait;

    public function __construct(
        protected StoreService $storeService,
        protected ItemService $itemService,
        protected ReviewService $reviewService
    ) {}

    public function index(Request $request, $filter_data = 'all')
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceProviderService::class)->apiList($this->serviceFilters($request), $filter_data));
        }

        $this->applyZoneIds($request);

        $payload = $this->cachedPayload('api.stores_list', $request, $this->storeListFilters($request, $filter_data), function () use ($request, $filter_data) {
            $stores = $this->storeService->getList($this->storeListFilters($request, $filter_data), $this->pageParams($request));
            $stores['stores'] = $this->renderStores($request, $stores['stores']);

            return [
                'data' => $stores['stores'] ?? $stores['data'] ?? [],
                'pagination' => $this->paginateFormatter($stores['paginator']),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function visitAgain(Request $request)
    {
        $this->applyZoneIds($request);

        $payload = $this->cachedPayload('api.visit_again', $request, ['geo' => $this->geoBucket($request)], function () use ($request) {
            $stores = $this->storeService->getVisitAgainList([
                'zone_id' => $request->header('zoneId'),
                'module_id' => getModuleId($request->header('moduleId')),
                'longitude' => $request->header('longitude') ?? 0,
                'latitude' => $request->header('latitude') ?? 0,
                'user_id' => auth('api')->id(),
            ], $this->pageParams($request));

            return [
                'data' => $this->storeService->buildVisitAgainRows($stores->items(), $request->header('zoneId')),
                'pagination' => $this->paginateFormatter($stores),
            ];
        }, perUser: true);

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function latest(Request $request)
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceProviderService::class)->apiLatest($this->serviceFilters($request)));
        }

        $this->applyZoneIds($request);

        $payload = $this->cachedPayload('api.stores_latest', $request, $this->storeListFilters($request), function () use ($request) {
            $stores = $this->storeService->getLatestList($this->storeListFilters($request), $this->pageParams($request));
            $stores['stores'] = $this->renderStores($request, $stores['stores']);

            return [
                'data' => $stores['stores'] ?? $stores['data'] ?? [],
                'pagination' => $this->paginateFormatter($stores['paginator']),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function verified(Request $request)
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceProviderService::class)->apiVerified($this->serviceFilters($request)));
        }

        $this->applyZoneIds($request);

        $stores = $this->storeService->getVerifiedList($this->storeListFilters($request), $this->pageParams($request));
        $stores['stores'] = $this->renderStores($request, $stores['stores']);

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $stores['stores'] ?? $stores['data'] ?? [],
            'pagination' => $this->paginateFormatter($stores['paginator']),
        ]);
    }

    public function distanceWise(Request $request)
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceProviderService::class)->apiDistance($this->serviceFilters($request)));
        }

        $this->applyZoneIds($request);

        $stores = $this->storeService->getDistanceWiseList($this->storeListFilters($request), $this->pageParams($request));
        $stores['stores'] = $this->renderStores($request, $stores['stores']);

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $stores['stores'] ?? $stores['data'] ?? [],
            'pagination' => $this->paginateFormatter($stores['paginator']),
        ]);
    }

    public function popular(Request $request)
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceProviderService::class)->apiPopular($this->serviceFilters($request)));
        }

        $this->applyZoneIds($request);
        $payload = $this->cachedPayload('api.stores_popular', $request, $this->storeListFilters($request), function () use ($request) {
            $stores = $this->storeService->getPopularList($this->storeListFilters($request), $this->pageParams($request));
            $stores['stores'] = $this->renderStores($request, $stores['stores']);

            return [
                'data' => $stores['stores'] ?? $stores['data'] ?? [],
                'pagination' => $this->paginateFormatter($stores['paginator']),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function discounted(Request $request)
    {
        $this->applyZoneIds($request);
        $stores = $this->storeService->getDiscountedList($this->storeListFilters($request), $this->pageParams($request));
        $stores['stores'] = $this->renderStores($request, $stores['stores']);

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $stores['stores'] ?? $stores['data'] ?? [],
            'pagination' => $this->paginateFormatter($stores['paginator']),
        ]);
    }

    public function topRated(Request $request)
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceProviderService::class)->apiTopRated($this->serviceFilters($request)));
        }

        $this->applyZoneIds($request);
        $stores = $this->storeService->getTopRatedList($this->storeListFilters($request), $this->pageParams($request));
        $stores['stores'] = $this->renderStores($request, $stores['stores']);

        usort($stores['stores'], function ($a, $b) {
            $key = 'avg_rating';

            return $b[$key] - $a[$key];
        });

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $stores['stores'] ?? $stores['data'] ?? [],
            'pagination' => $this->paginateFormatter($stores['paginator']),
        ]);
    }

    public function popularItems(Request $request, mixed $id)
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceProviderService::class)->apiPopularServices($this->serviceFilters($request), $id));
        }

        $this->applyZoneIds($request);

        $items = $this->itemService->getStorePopularList(
            $id, $this->pageParams($request), $this->storeItemFilters($request, $id)
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemListResource::collection($items->items())->toArray($request),
            'pagination' => $this->paginateFormatter($items),
        ]);
    }

    public function show(Request $request, mixed $id)
    {
        if (! service_api_module_active() && addon_published_status('Service')) {
            if ($serviceModule = $this->storeService->isServiceModuleStore($id)) {
                Config::set('module.current_module_data', $serviceModule);
            }
        }

        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceProviderService::class)->apiDetails($this->serviceFilters($request), $id));
        }

        $store = $this->storeService->findDetail($id, $request->header('longitude'), $request->header('latitude'));

        if (! $store) {
            return $this->responseFormatter(config('response.default_200'), $store);
        }

        $this->storeService->recordVisit($store->id, auth('api')->id());

        $store = $this->storeService->loadDetailRelations($store);

        return $this->responseFormatter(config('response.default_200'), (new StoreShowResource(
            $store, $this->storeService->getDetailExtras($store)
        ))->toArray($request));
    }

    public function search(StoreSearchRequest $request)
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceProviderService::class)->apiSearch($this->serviceFilters($request)));
        }

        $this->applyZoneIds($request);

        if (auth('api')->check()) {
            app(PersonalizationService::class)->recordSearchAction(auth('api')->id(), $request['name'], config('module.current_module_data') ? (int) config('module.current_module_data')['id'] : null);
        }

        $stores = $this->storeService->searchList($this->storeListFilters($request), $this->pageParams($request));
        $stores['stores'] = $this->renderStores($request, $stores['stores'], ['with_items' => true]);

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $stores['stores'] ?? $stores['data'] ?? [],
            'pagination' => $this->paginateFormatter($stores['paginator']),
        ]);
    }

    public function reviews(StoreReviewListRequest $request)
    {
        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceProviderService::class)->apiStoreReviews($this->serviceFilters($request)));
        }
        $id = $request['store_id'];

        $reviews = $this->reviewService->getStoreReviewList($id, $this->pageParams($request));

        $storage = [];
        foreach ($reviews as $temp) {
            $temp['attachment_full_url'] = $temp->attachment_full_url;
            $temp['attachment'] = json_decode($temp['attachment']);
            $temp['item_name'] = null;
            $temp['item_image'] = null;
            $temp['customer_name'] = null;
            if ($item = $temp->item) {
                $temp['item_name'] = $item->name;
                $temp['item_image'] = $item->image;
                $temp['item_image_full_url'] = $item->image_full_url;
                if (count($item->translations) > 0) {
                    $translate = array_column($item->translations->toArray(), 'value', 'key');
                    $temp['item_name'] = $translate['name'];
                }
                unset($temp->item);
                $temp['item'] = (new ItemListResource($item))->toArray($request);
            }
            if ($temp->customer) {
                $temp['customer_name'] = $temp->customer->f_name.' '.$temp->customer->l_name;
            }

            unset($temp['customer']);
            array_push($storage, $temp);
        }

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $storage,
            'pagination' => $this->paginateFormatter($reviews),
        ]);
    }

    public function recommended(Request $request)
    {

        if (service_api_module_active()) {
            return $this->serviceResult(app(ServiceProviderService::class)->apiRecommended($this->serviceFilters($request)));
        }

        $this->applyZoneIds($request);
        $payload = $this->cachedPayload('api.stores_recommended', $request, $this->storeListFilters($request), function () use ($request) {
            $stores = $this->storeService->getRecommendedList($this->storeListFilters($request), $this->pageParams($request));
            $stores['stores'] = $this->renderStores($request, $stores['stores']);

            return [
                'data' => $stores['stores'] ?? $stores['data'] ?? [],
                'pagination' => $this->paginateFormatter($stores['paginator']),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function topOfferNearMe(Request $request)
    {
        $this->applyZoneIds($request);

        $context = $this->storeListFilters($request) + ['sort_by' => $request->sort_by, 'halal' => $request->halal, 'geo' => $this->geoBucket($request)];

        $payload = $this->cachedPayload('api.stores_top_offer', $request, $context, function () use ($request) {
            $stores = $this->storeService->getTopOfferNearMeList($this->storeListFilters($request) + ['sort_by' => $request->sort_by, 'halal' => $request->halal], $this->pageParams($request));
            $stores['stores'] = $this->renderStores($request, $stores['stores']);

            return [
                'data' => $stores['stores'] ?? $stores['data'] ?? [],
                'pagination' => $this->paginateFormatter($stores['paginator']),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function quickDelivery(Request $request)
    {
        $this->applyZoneIds($request);
        $isAd = filter_var($request->query('ad', false), FILTER_VALIDATE_BOOLEAN);
        $withItems = isset($request->with_items) ? $request->with_items : false;

        $stores = $this->storeService->getQuickDeliveryList($this->storeListFilters($request) + ['is_ad' => $isAd, 'with_items' => $withItems], $this->pageParams($request));

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $stores['stores'] ?? $stores['data'] ?? [],
            'pagination' => $this->paginateFormatter($stores['paginator']),
        ]);
    }

    public function exclusiveDeals(Request $request)
    {
        $this->applyZoneIds($request);

        $data = $this->storeService->getExclusiveDealsList($this->storeListFilters($request), $this->pageParams($request));

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $data['stores'] ?? $data['data'] ?? [],
            'pagination' => $this->paginateFormatter($data['paginator']),
        ]);
    }

    private function renderStores(Request $request, mixed $stores, array $options = []): array
    {
        $stores = $this->storeService->loadListRelations($stores);

        return StoreListResource::renderList($stores, $options + [
            'advertised_store_ids' => $this->storeService->advertisedIdsFor($stores, $request->header('zoneId')),
        ]);
    }
}
