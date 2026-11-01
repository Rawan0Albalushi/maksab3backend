<?php

namespace App\Http\Controllers\Api\V1\Customer\Wishlist;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Service\ServiceResource;
use App\Http\Requests\Customer\Wishlist\WishlistTargetRequest;
use App\Http\Resources\Common\Item\ItemResource;
use App\Http\Resources\Customer\Store\StoreResource;
use App\Services\Customer\WishlistService;
use App\Traits\Item\ItemFilterTrait;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends BaseApiController
{
    use ItemFilterTrait;
    use ApiRequestContextTrait;

    private const RATING_INPUTS = [
        'rating', 'rating_plus', 'rating_count', 'rating_1', 'rating_1_plus', 'rating_2', 'rating_2_plus',
        'rating_3', 'rating_3_plus', 'rating_4', 'rating_4_plus', 'rating_5',
        'price', 'min_price', 'max_price',
    ];

    public function __construct(
        private readonly WishlistService $wishlistService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $wishlists = $this->wishlistService->getList(
            filters: $this->filters($request),
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'item' => ItemResource::collection($wishlists->getCollection()->pluck('item')->filter()->values()),
            'store' => StoreResource::collection($wishlists->getCollection()->pluck('store')->filter()->values()),
            'service' => $this->services($wishlists->getCollection()),
            'pagination' => $this->paginateFormatter($wishlists),
        ]);
    }

    public function store(WishlistTargetRequest $request): JsonResponse
    {
        if ($request->targetCount() > 1) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'data', 'message' => translate('messages.Can not add both food and restaurant at same time')],
            ]);
        }

        $userId = $request->user()->id;

        if ($this->wishlistService->exists($userId, $request->target())) {
            return $this->responseFormatter(config('response.already_exists_409'), errors: [
                ['code' => 'wishlist', 'message' => translate('messages.Already in wishlist')],
            ]);
        }

        $this->wishlistService->create($request->target() + ['user_id' => $userId]);

        return $this->responseFormatter(config('response.default_store_201'), [
            'message' => translate($request->targetLabel('added to')),
        ]);
    }

    public function destroy(WishlistTargetRequest $request): JsonResponse
    {
        $wishlist = $this->wishlistService->findOwned($request->user()->id, $request->target());

        if (! $wishlist) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $this->wishlistService->delete($wishlist);

        $label = $request->targetLabel('removed from');

        return $this->responseFormatter([
            'identical_code' => 'default_delete_200',
            'message' => $label,
            'http_response_code' => 200,
        ], [
            'message' => translate($label),
        ]);
    }

    private function services(mixed $wishlists): mixed
    {
        $services = $wishlists->pluck('service')->filter()->values();

        if ($services->isEmpty()) {
            return [];
        }

        return ServiceResource::renderList($services, $this->wishlistService->favoriteServiceIds(
            auth('api')->id(),
            $services->pluck('id')->filter()->all()
        ));
    }

    private function filters(Request $request): array
    {
        $searchFilters = $this->resolveSearchFilters($request, $request['filter'] ?? null);
        [$itemFilterBy, $itemSortBy] = $this->rankTopRatedInsteadOfExcluding($searchFilters['filter_by'], $searchFilters['sort_by']);

        $storeFilter = $this->storeFilterInputs($request);
        [$storeQuickAction, $storeSortBy] = $this->rankTopRatedInsteadOfExcluding(
            $this->normalizeFilterValues($storeFilter['quick_action'] ?? null),
            $this->normalizeSortValue($storeFilter['sort_by'] ?? 'default'),
        );
        $storeFilter['quick_action'] = $storeQuickAction;
        $storeFilter['sort_by'] = $storeSortBy;

        return [
            'user_id' => $request->user()->id,
            'zone_ids' => $this->zoneIds($request),
            'module_id' => $this->wishlistModuleId($request),
            'longitude' => $request->header('longitude'),
            'latitude' => $request->header('latitude'),
            'type' => $request->query('type', 'all'),
            'search' => $request->query('search'),
            'filter_list' => $searchFilters['filter_list'],
            'item_filters' => ['sort_by' => $itemSortBy, 'filter_by' => $itemFilterBy],
            'store_filters' => $storeFilter,
            'inputs' => $request->only(self::RATING_INPUTS),
        ];
    }

    private function wishlistModuleId(Request $request): ?int
    {
        $moduleHeader = $request->header('moduleId');
        $moduleId = $moduleHeader ? getModuleId($moduleHeader) : $this->currentModuleId();

        return is_numeric($moduleId) ? (int) $moduleId : null;
    }

    private function rankTopRatedInsteadOfExcluding(array $filterBy, string $sortBy): array
    {
        if (! in_array('top_rated', $filterBy, true)) {
            return [$filterBy, $sortBy];
        }

        return [
            array_values(array_diff($filterBy, ['top_rated'])),
            $sortBy === 'default' ? 'high_rated' : $sortBy,
        ];
    }
}
