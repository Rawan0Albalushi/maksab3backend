<?php

namespace App\Services\Order;

use App\CentralLogics\Helpers;
use App\Models\Cart;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Services\BaseService;
use App\Services\Item\AddonService;
use App\Services\Store\StoreService;
use App\Traits\Customer\PersonalizationTrait;
use App\Traits\Item\ItemRelationsTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Rental\Services\Cart\RentalCartService;
use Modules\Rental\Services\Cart\RentalCartUserDataService;

class CartService extends BaseService
{
    use ItemRelationsTrait;
    use PersonalizationTrait;

    public function deleteByZone(mixed $zoneId): void
    {
        $cartIds = Cart::whereHas('item.store', fn ($query) => $query->where('zone_id', $zoneId))
            ->pluck('id')->toArray();

        if (count($cartIds) > 0) {
            Cart::destroy($cartIds);
        }
    }

    public function getPaginatedList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->paginateCollection($this->getList($filters), $paginate);
    }

    public function getGroupedByStore(array $filters = []): SupportCollection
    {
        $carts = $this->getList($filters);

        $storeIds = $carts->pluck('store_id')->filter()->unique()->values()->all();
        $stores = empty($storeIds)
            ? collect()
            : app(StoreService::class)->getCartStores($storeIds, $filters['longitude'] ?? 0, $filters['latitude'] ?? 0);

        return $carts
            ->groupBy(fn ($cart) => $cart->store_id ?? data_get($cart, 'item.store_id') ?? 'unknown')
            ->sortByDesc(fn ($storeCarts) => $storeCarts->max('id'))
            ->map(fn ($storeCarts, $storeId) => [
                'store' => $stores[$storeId] ?? $storeCarts->first()?->store,
                'store_id' => is_numeric($storeId) ? (int) $storeId : null,
                'carts' => $storeCarts->values(),
            ])
            ->values();
    }

    public function create(array $data): Cart
    {
        $cart = new Cart;
        $cart->user_id = $data['user_id'];
        $cart->module_id = $data['module_id'];
        $cart->store_id = $data['store_id'];
        $cart->item_id = $data['item_id'];
        $cart->is_guest = $data['is_guest'];
        $cart->add_on_ids = json_encode($data['add_on_ids'] ?? []);
        $cart->add_on_qtys = json_encode($data['add_on_qtys'] ?? []);
        $cart->item_type = $data['item_type'];
        $cart->reel_id = $data['reel_id'];
        $cart->price = $data['price'];
        $cart->quantity = $data['quantity'];
        $cart->variation = json_encode($data['variation'] ?? []);
        $cart->save();

        if (! $data['is_guest'] && $data['item_type'] === Item::class) {
            $this->recordItemAction((int) $data['user_id'], (int) $data['item_id'], 'cart');
        }

        return $cart;
    }

    public function update(Cart $cart, array $data): Cart
    {
        $cart->user_id = $data['user_id'];
        $cart->module_id = $data['module_id'];
        $cart->store_id = $data['store_id'];
        $cart->is_guest = $data['is_guest'];
        if (array_key_exists('add_on_ids', $data)) {
            $cart->add_on_ids = json_encode($data['add_on_ids'] ?? []);
        }

        if (array_key_exists('add_on_qtys', $data)) {
            $cart->add_on_qtys = json_encode($data['add_on_qtys'] ?? []);
        }

        $cart->price = $data['price'];
        $cart->quantity = $data['quantity'];
        $cart->variation = json_encode(array_values($data['variation'] ?? []));
        $cart->save();

        return $cart;
    }

    public function delete(Cart $cart): bool
    {
        return (bool) $cart->delete();
    }

    public function mergeGuestCart(mixed $userId, mixed $guestId): void
    {
        if (! $guestId || ! $userId) {
            return;
        }

        if (Cart::where(['user_id' => $guestId, 'is_guest' => 1])->exists()) {
            Cart::where(['user_id' => $userId, 'is_guest' => 0])->delete();
        }

        Cart::where('user_id', $guestId)->update(['user_id' => $userId, 'is_guest' => 0]);

        if (! addon_published_status('Rental')) {
            return;
        }

        if (app(RentalCartService::class)->guestRowsExist($guestId)) {
            app(RentalCartService::class)->deleteForUser($userId);
        }

        if (app(RentalCartUserDataService::class)->guestRowsExist($guestId)) {
            app(RentalCartUserDataService::class)->deleteForUser($userId);
        }

        app(RentalCartService::class)->transferGuestRows($guestId, $userId);
        app(RentalCartUserDataService::class)->transferGuestRows($guestId, $userId);
    }

    public function clear(array $filters): bool
    {
        return (bool) $this->ownedQuery($filters)
            ->when($filters['store_id'] ?? null, fn ($q, $storeId) => $q->where('store_id', $storeId))
            ->delete();
    }

    public function count(array $filters): int
    {
        return $this->ownedQuery($filters)->count();
    }

    public function findOwned(mixed $cartId, array $owner): ?Cart
    {
        return Cart::where('id', $cartId)
            ->where('user_id', $owner['user_id'])
            ->where('is_guest', $owner['is_guest'])
            ->first();
    }

    public function findItem(string $model, mixed $itemId): Item|ItemCampaign|null
    {
        return $this->itemClass($model)::with('module:id,module_type')->find($itemId);
    }

    public function itemClass(string $model): string
    {
        return $model === 'Item' || $model === Item::class ? Item::class : ItemCampaign::class;
    }

    public function alreadyInCart(array $owner, string $itemType, mixed $itemId, mixed $variation): bool
    {
        return Cart::where('item_id', $itemId)
            ->where('item_type', $itemType)
            ->where('variation', $this->storedVariation($variation))
            ->where('user_id', $owner['user_id'])
            ->where('is_guest', $owner['is_guest'])
            ->where('module_id', $owner['module_id'])
            ->exists();
    }

    public function exceedsMaxQuantity(mixed $item, int $quantity): bool
    {
        return $item->maximum_cart_quantity && $quantity > $item->maximum_cart_quantity;
    }

    public function requiresVariation(mixed $item): bool
    {
        foreach (['food_variations', 'variations'] as $column) {
            $variations = $item->getAttributes()[$column] ?? null;
            $variations = is_array($variations) ? $variations : (json_decode($variations ?? '[]', true) ?: []);

            foreach ($variations as $variation) {
                if (in_array($variation['required'] ?? 'off', ['on', '1', 1, true], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function outOfStockMessage(mixed $item, mixed $variation, int $quantity): ?string
    {
        $moduleType = $item->module?->module_type ?? null;

        if (! $moduleType || ! config('module.'.$moduleType.'.stock')) {
            return null;
        }

        $variationInput = is_array($variation) ? array_values($variation) : [];
        $itemVariations = json_decode($item['variations'] ?? '[]', true) ?: [];

        $stock = (count($itemVariations) > 0 && count($variationInput) > 0)
            ? (int) (Helpers::variation_price($item, json_encode($variationInput))['stock'] ?? 0)
            : (int) ($item->stock ?? 0);

        return $quantity > $stock
            ? trim(($item->name ?? '').' '.translate('messages.Is out of stock'))
            : null;
    }

    public function resolveStoreId(mixed $storeId): ?int
    {
        if ($storeId === null || $storeId === '') {
            return null;
        }

        return is_numeric($storeId) ? (int) $storeId : app(StoreService::class)->findIdBySlug($storeId);
    }

    public function itemStoreId(mixed $item): ?int
    {
        return $item?->store_id ?? $item?->store?->id;
    }

    public function getCheckoutList(mixed $userId, mixed $isGuest, mixed $storeId, mixed $moduleId, mixed $cartId = null): mixed
    {
        return Cart::where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->where('store_id', $storeId)
            ->where('module_id', $moduleId)
            ->when($cartId, fn ($query) => $query->where('id', $cartId))
            ->get()
            ->map(function ($cart) {
                $cart->add_on_ids = is_array($cart->add_on_ids) ? $cart->add_on_ids : json_decode($cart->add_on_ids, true);
                $cart->add_on_qtys = is_array($cart->add_on_qtys) ? $cart->add_on_qtys : json_decode($cart->add_on_qtys, true);
                $cart->variation = is_array($cart->variation) ? $cart->variation : json_decode($cart->variation, true);

                return $cart;
            });
    }

    public function getMatchingLines(mixed $itemId, string $itemType, mixed $userId, mixed $isGuest, mixed $moduleId): mixed
    {
        return Cart::where('item_id', $itemId)
            ->where('item_type', $itemType)
            ->where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->get();
    }

    public function claimGuestRows(mixed $guestId, mixed $userId): void
    {
        Cart::where('user_id', $guestId)->update(['user_id' => $userId, 'is_guest' => 0]);
    }

    private function getList(array $filters = []): Collection
    {
        $carts = $this->ownedQuery($filters)
            ->when($filters['store_id'] ?? null, fn ($q, $storeId) => $q->where('store_id', $storeId))
            ->with(['item' => fn (MorphTo $morphTo) => $morphTo->morphWith($this->cartItemRelations())])
            ->get();

        $this->attachSelections($carts);

        return $carts->filter(fn ($cart) => $cart->item)->values();
    }

    private function ownedQuery(array $filters)
    {
        return Cart::where('user_id', $filters['user_id'] ?? null)
            ->where('is_guest', $filters['is_guest'] ?? 0)
            ->where('module_id', $filters['module_id'] ?? null);
    }

    private function cartItemRelations(): array
    {
        $shared = $this->itemRelationSet();
        $campaignSafe = array_diff_key($shared, array_flip(['rating', 'ecommerce_item_details', 'flashSaleItems']));
        $generic = ['generic' => fn ($query) => $query->select('generic_names.id', 'generic_names.generic_name')];

        return [
            Item::class => $shared + $generic + [
                'pharmacy_item_details' => fn ($query) => $query->select('id', 'item_id', 'is_prescription_required'),
            ],
            ItemCampaign::class => $campaignSafe + $generic,
        ];
    }

    private function attachSelections(Collection $carts): void
    {
        foreach ($carts as $cart) {
            $cart->setAttribute('selected_variation', $this->decodeNested($cart->getRawOriginal('variation')));
            $cart->setAttribute('selected_addon_ids', $this->decodeNested($cart->getRawOriginal('add_on_ids')));
            $cart->setAttribute('selected_addon_qtys', $this->decodeNested($cart->getRawOriginal('add_on_qtys')));
        }

        $addonIds = $carts
            ->flatMap(fn ($cart) => $cart->getAttribute('selected_addon_ids'))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $addons = empty($addonIds)
            ? collect()
            : app(AddonService::class)->getByIdsWithTaxes($addonIds);

        foreach ($carts as $cart) {
            $qtys = $cart->getAttribute('selected_addon_qtys');
            $selected = [];

            foreach (array_values($cart->getAttribute('selected_addon_ids')) as $index => $addonId) {
                $addon = $addons[(int) $addonId] ?? null;

                if (! $addon) {
                    continue;
                }

                $selected[] = [
                    'id' => (int) $addon->id,
                    'name' => $addon->name,
                    'price' => (float) $addon->price,
                    'store_id' => (int) $addon->store_id,
                    'status' => (int) $addon->status,
                    'addon_category_id' => $addon->addon_category_id,
                    'tax_ids' => $addon->taxVats->pluck('tax_id')->values()->all(),
                    'isChecked' => true,
                    'quantity' => (int) ($qtys[$index] ?? 1),
                ];
            }

            $cart->setAttribute('selected_addons', $selected);
        }
    }

    private function storedVariation(mixed $variation): string
    {
        return json_encode(json_encode($variation ?? []));
    }

    private function decodeNested(mixed $value): array
    {
        for ($pass = 0; $pass < 2 && is_string($value); $pass++) {
            $decoded = json_decode($value, true);

            if ($decoded === null) {
                return [];
            }

            $value = $decoded;
        }

        return is_array($value) ? $value : [];
    }
}
