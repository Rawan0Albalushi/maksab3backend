<?php

namespace App\Http\Controllers\Api\V1\Customer\Cart;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Cart\CartAddMultipleRequest;
use App\Http\Requests\Customer\Cart\CartAddRequest;
use App\Http\Requests\Customer\Cart\CartBogoRequest;
use App\Http\Requests\Customer\Cart\CartBundleRequest;
use App\Http\Requests\Customer\Cart\CartDiscountEligibilityRequest;
use App\Http\Requests\Customer\Cart\CartClearRequest;
use App\Http\Requests\Customer\Cart\CartDeleteItemRequest;
use App\Http\Requests\Customer\Cart\CartListRequest;
use App\Http\Requests\Customer\Cart\CartUpdateRequest;
use App\Http\Resources\Customer\Cart\CartResource;
use App\Http\Resources\Customer\Cart\CartStoreGroupResource;
use App\Models\Store;
use App\Services\Order\CartService;
use App\Services\Promotion\BundleCartService;
use App\Services\Promotion\BundleGroupPresenter;
use App\Services\Promotion\BogoCartService;
use App\Services\Promotion\BogoGroupPresenter;
use App\Services\Promotion\BogoOfferCustomerService;
use App\Services\Store\StoreService;
use App\Traits\Api\ApiRequestContextTrait;
use App\Traits\Api\ModuleDelegationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Service\Services\ServiceCartService;

class CartController extends BaseApiController
{
    use ApiRequestContextTrait;
    use ModuleDelegationTrait;

    public function __construct(
        private readonly CartService $cartService
    ) {}

    public function index(CartListRequest $request): JsonResponse
    {
        if ($this->isServiceModuleContext()) {
            return $this->serviceResult(app(ServiceCartService::class)->getCarts($this->cartPayload($request)));
        }

        return $this->cartsResponse($request->filters());
    }

    public function groupedByStore(Request $request): JsonResponse
    {
        if ($this->isServiceModuleContext()) {
            return $this->serviceResult(app(ServiceCartService::class)->getAllCarts($this->cartPayload($request)));
        }

        $owner = $this->cartOwner($request);

        $groups = $this->cartService->getGroupedByStore(array_merge($owner, [
            'longitude' => $request->header('longitude'),
            'latitude' => $request->header('latitude'),
        ]))->map(fn (array $group) => array_merge($group, [
            'carts' => $this->foldPromotionGroups($group['carts'], $owner),
        ]));

        return $this->responseFormatter(
            config('response.default_200'),
            CartStoreGroupResource::collection($groups)
        );
    }

    public function store(CartAddRequest $request): JsonResponse
    {
        if ($this->isServiceModuleContext()) {
            return $this->serviceResult(app(ServiceCartService::class)->addToCart($this->cartPayload($request)));
        }

        $payload = $request->payload();

        if ($error = $this->rejectInvalidLine($payload)) {
            return $error;
        }

        $this->cartService->create($this->cartRow($payload));

        return $this->cartsResponse($request->filters(), config('response.default_store_201'));
    }

    public function storeMultiple(CartAddMultipleRequest $request): JsonResponse
    {
        if ($this->isServiceModuleContext()) {
            return $this->serviceResult(app(ServiceCartService::class)->addToCartMultiple($this->cartPayload($request)));
        }

        $owner = $request->filters();

        foreach ($request->itemList() as $line) {
            $payload = array_merge($owner, [
                'item_id' => (int) $line['item_id'],
                'model' => $line['model'],
                'price' => $line['price'],
                'quantity' => (int) $line['quantity'],
                'variation' => $line['variation'] ?? [],
                'add_on_ids' => $line['add_on_ids'] ?? [],
                'add_on_qtys' => $line['add_on_qtys'] ?? [],
                'reel_id' => Helpers::resolve_reel_id(
                    isset($line['reel_id']) ? (int) $line['reel_id'] : null,
                    (int) $line['item_id']
                ),
            ]);

            if ($error = $this->rejectInvalidLine($payload)) {
                return $error;
            }

            $this->cartService->create($this->cartRow($payload));
        }

        return $this->cartsResponse($owner, config('response.default_store_201'));
    }

    public function update(CartUpdateRequest $request): JsonResponse
    {
        if ($this->isServiceModuleContext()) {
            return $this->serviceResult(app(ServiceCartService::class)->updateCart($this->cartPayload($request)));
        }

        $owner = $request->filters();
        $cart = $this->cartService->findOwned($request->cartId(), $owner);

        if (! $cart) {
            return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'cart', 'message' => translate('No data found')],
            ]);
        }

        if ($bundleGuard = $this->rejectBundleLine($cart)) {
            return $bundleGuard;
        }

        $item = $this->cartService->findItem($cart->item_type, $cart->item_id);

        if (! $item) {
            return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'cart_item', 'message' => translate('No data found')],
            ]);
        }

        $variation = $request->variation();

        if (empty($variation) && $this->cartService->requiresVariation($item)) {
            return $this->rejection('variation', translate('messages.Variation is required'));
        }

        if ($message = $this->cartService->outOfStockMessage($item, $variation, (int) $request->input('quantity'))) {
            return $this->rejection('stock', $message);
        }

        if ($this->cartService->exceedsMaxQuantity($item, (int) $request->input('quantity'))) {
            return $this->rejection('cart_item_limit', translate('messages.Maximum cart quantity exceeded'));
        }

        $this->cartService->update($cart, array_merge($request->payload(), [
            'store_id' => $this->cartService->itemStoreId($item),
        ]));

        return $this->cartsResponse($owner, config('response.default_update_200'));
    }

    public function destroy(CartDeleteItemRequest $request): JsonResponse
    {
        if ($this->isServiceModuleContext()) {
            return $this->serviceResult(app(ServiceCartService::class)->removeCartItem($this->cartPayload($request)));
        }

        $owner = $request->filters();
        $cart = $this->cartService->findOwned($request->cartId(), $owner);

        if (! $cart) {
            return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'cart', 'message' => translate('No data found')],
            ]);
        }

        if ($bundleGuard = $this->rejectBundleLine($cart)) {
            return $bundleGuard;
        }

        $this->cartService->delete($cart);

        return $this->cartsResponse($owner, config('response.default_delete_200'));
    }

    public function destroyAll(CartClearRequest $request): JsonResponse
    {
        if ($this->isServiceModuleContext()) {
            return $this->serviceResult(app(ServiceCartService::class)->removeCart($this->cartPayload($request)));
        }

        $owner = $request->filters();
        $this->cartService->clear($owner);

        return $this->cartsResponse($owner, config('response.default_delete_200'));
    }

    public function storeBundle(CartBogoRequest $request): JsonResponse
    {
        $owner = $this->cartOwner($request);

        $result = app(BogoCartService::class)->addBundle($request->payload() + $owner + [
            'zone_ids' => $this->applyZoneIds($request),
        ]);

        if ($result['status_code'] >= 400) {
            return $this->errorResponse($this->statusConfig($result['status_code']), $result['message'], $result['code']);
        }

        return $this->bundleResponse($owner, $result, config('response.default_store_201'));
    }

    public function updateBundle(CartBogoRequest $request): JsonResponse
    {
        $owner = $this->cartOwner($request);

        $result = app(BogoCartService::class)->updateBundle($request->payload() + $owner);

        if ($result['status_code'] >= 400) {
            return $this->errorResponse($this->statusConfig($result['status_code']), $result['message'], $result['code']);
        }

        return $this->bundleResponse($owner, $result, config('response.default_update_200'));
    }

    public function destroyBundle(CartBogoRequest $request): JsonResponse
    {
        $owner = $this->cartOwner($request);

        $result = app(BogoCartService::class)->removeBundle($request->payload() + $owner);

        if ($result['status_code'] >= 400) {
            return $this->errorResponse($this->statusConfig($result['status_code']), $result['message'], $result['code']);
        }

        return $this->cartsResponse($owner, config('response.default_delete_200'));
    }

    public function storeBundlePackage(CartBundleRequest $request): JsonResponse
    {
        $owner = $this->cartOwner($request);

        $result = app(BundleCartService::class)->addBundle($request->payload() + $owner + [
            'zone_ids' => $this->applyZoneIds($request),
        ]);

        if ($result['status_code'] >= 400) {
            return $this->errorResponse($this->statusConfig($result['status_code']), $result['message'], $result['code']);
        }

        return $this->bundlePackageResponse($owner, $result, config('response.default_store_201'));
    }

    public function updateBundlePackage(CartBundleRequest $request): JsonResponse
    {
        $owner = $this->cartOwner($request);

        $result = app(BundleCartService::class)->updateBundle($request->payload() + $owner);

        if ($result['status_code'] >= 400) {
            return $this->errorResponse($this->statusConfig($result['status_code']), $result['message'], $result['code']);
        }

        return $this->bundlePackageResponse($owner, $result, config('response.default_update_200'));
    }

    public function destroyBundlePackage(CartBundleRequest $request): JsonResponse
    {
        $owner = $this->cartOwner($request);

        $result = app(BundleCartService::class)->removeBundle($request->payload() + $owner);

        if ($result['status_code'] >= 400) {
            return $this->errorResponse($this->statusConfig($result['status_code']), $result['message'], $result['code']);
        }

        return $this->cartsResponse($owner, config('response.default_delete_200'));
    }

    private function bundlePackageResponse(array $owner, array $result, array $config): JsonResponse
    {
        $carts = $this->cartService->getPaginatedList($owner, [
            'per_page' => $this->perPage(request()),
            'page' => $this->page(request()),
        ]);

        return $this->responseFormatter($config, [
            'bundle_group_id' => $result['bundle_group_id'],
            'quantity' => $result['quantity'],
            'store_id' => $result['store_id'],
            'data' => $this->presentCarts($carts),
            'pagination' => $this->paginateFormatter($carts),
        ]);
    }

    public function discountEligibility(CartDiscountEligibilityRequest $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $store = app(StoreService::class)->findActiveInZones(
            $request->input('store_id'),
            $this->zoneIds($request),
            $this->currentModuleId()
        );

        if (! $store) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'store');
        }

        // BogoCartService::qualifyingTotal() prices lines by looking them up in the `items`
        // table -- for a service-module cart, `item_id` points at a Service, not an Item, so it
        // must be routed to ServiceCartService's own implementation instead (no BOGO/happy-hour
        // in the service module, but the same store-wide-discount concept applies).
        $eligibility = $this->isServiceModuleContext()
            ? app(ServiceCartService::class)->discountEligibility($this->cartOwner($request), $store)
            : app(BogoCartService::class)->discountEligibility($this->cartOwner($request), $store);

        return $this->responseFormatter(config('response.default_200'), $eligibility);
    }

    private function rejectBundleLine(mixed $cart): ?JsonResponse
    {
        if (app(BundleCartService::class)->belongsToBundle($cart)) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('messages.Use the bundle actions to change this bundle'),
                'bundle'
            );
        }

        if (! app(BogoCartService::class)->belongsToBundle($cart)) {
            return null;
        }

        return $this->errorResponse(
            config('response.forbidden_403'),
            translate('messages.Use the offer actions to change this bundle'),
            'bogo_offer'
        );
    }

    private function bundleResponse(array $owner, array $result, array $config): JsonResponse
    {
        $carts = $this->cartService->getPaginatedList($owner, [
            'per_page' => $this->perPage(request()),
            'page' => $this->page(request()),
        ]);

        return $this->responseFormatter($config, [
            'bogo_group_id' => $result['bogo_group_id'],
            'quantity' => $result['quantity'],
            'store_id' => $result['store_id'],
            'data' => $this->presentCarts($carts),
            'pagination' => $this->paginateFormatter($carts),
        ]);
    }

    private function cartsResponse(array $filters, ?array $config = null): JsonResponse
    {
        $carts = $this->cartService->getPaginatedList($filters, [
            'per_page' => $this->perPage(request()),
            'page' => $this->page(request()),
        ]);

        return $this->responseFormatter($config ?? config('response.default_200'), [
            'data' => $this->presentCarts($carts, $filters),
            'pagination' => $this->paginateFormatter($carts),
        ]);
    }

    private function presentCarts(mixed $carts, array $filters = []): array
    {
        $rows = $carts instanceof Collection ? $carts : collect($carts->items());

        return CartResource::collection(
            $this->foldPromotionGroups($rows, $filters ?: $this->cartOwner(request()))
        )->toArray(request());
    }

    private function foldPromotionGroups(Collection $rows, array $owner): Collection
    {
        $hasBogo = $rows->contains(fn ($row) => ! empty($row->bogo_group_id));
        $hasBundle = $rows->contains(fn ($row) => ! empty($row->bundle_group_id));

        if (! $hasBogo && ! $hasBundle) {
            return $rows;
        }

        $userId = $owner['user_id'] ?? null;
        $isGuest = (int) ($owner['is_guest'] ?? 0);

        $entries = $rows;

        if ($hasBogo) {
            $entries = collect(app(BogoGroupPresenter::class)->present(
                $entries, $userId, $isGuest, $this->cartHappyHourRate($rows, 'bogo_group_id')
            ));
        }

        if ($hasBundle) {
            $bundleStore = $this->cartGroupStore($rows, 'bundle_group_id');

            $entries = collect(app(BundleGroupPresenter::class)->present(
                $entries,
                $userId,
                $isGuest,
                app(BogoOfferCustomerService::class)->happyHourPercentageFor($bundleStore),
                app(BogoOfferCustomerService::class)->storeWidePromotionRunning($bundleStore)
            ));
        }

        return collect($entries)->values();
    }

    private function cartHappyHourRate(mixed $rows, string $groupColumn = 'bogo_group_id'): ?float
    {
        return app(BogoOfferCustomerService::class)
            ->happyHourPercentageFor($this->cartGroupStore($rows, $groupColumn));
    }

    /**
     * The store the cart's promotion group belongs to, or null when the cart has no such group.
     *
     * Reuses the store already hanging off the line's item when it is the right one, because the
     * cart has usually loaded it -- falling back to a lookup only when it is absent or belongs to
     * a different store than the line claims.
     */
    private function cartGroupStore(mixed $rows, string $groupColumn): ?Store
    {
        $row = $rows->firstWhere($groupColumn, '!=', null);
        $storeId = $row?->store_id;

        if (! $storeId) {
            return null;
        }

        $store = $row->relationLoaded('item') && $row->item?->relationLoaded('store')
            ? $row->item->store
            : null;

        return $store && (int) $store->getKey() === (int) $storeId
            ? $store
            : app(StoreService::class)->find($storeId);
    }

    private function cartRow(array $payload): array
    {
        return array_merge($payload, [
            'item_type' => $this->cartService->itemClass($payload['model']),
            'store_id' => $this->cartService->itemStoreId($payload['item']),
        ]);
    }

    private function rejectInvalidLine(array &$payload): ?JsonResponse
    {
        $item = $this->cartService->findItem($payload['model'], $payload['item_id']);

        if (! $item) {
            return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'cart_item', 'message' => translate('No data found')],
            ]);
        }

        $payload['item'] = $item;

        if ($message = $this->cartService->outOfStockMessage($item, $payload['variation'], $payload['quantity'])) {
            return $this->rejection('stock', $message);
        }

        $itemType = $this->cartService->itemClass($payload['model']);

        if ($this->cartService->alreadyInCart($payload, $itemType, $payload['item_id'], $payload['variation'])) {
            return $this->rejection('cart_item', translate('messages.Item already exists'));
        }

        if ($this->cartService->exceedsMaxQuantity($item, $payload['quantity'])) {
            return $this->rejection('cart_item_limit', translate('messages.Maximum cart quantity exceeded'));
        }

        return null;
    }

    private function rejection(string $code, string $message): JsonResponse
    {
        return $this->responseFormatter(config('response.unprocessable_entity_422'), errors: [
            ['code' => $code, 'message' => $message],
        ]);
    }

    private function cartPayload(Request $request): array
    {
        return $request->all() + [
            'user' => $request->user,
            'module_id' => config('module.current_module_data')['id'] ?? null,
        ];
    }
}
