<?php

namespace App\Builder\Resources;

use App\Builder\Support\ItemPricing;
use App\Models\Item;
use Modules\Builder\ValueObjects\Storefront\ItemCardDTO;

class ItemCardResource
{
    public static function fromCollection(iterable $items, array $context = []): array
    {
        $items = is_array($items) ? $items : iterator_to_array($items);

        // Show the LIVE review average on the card instead of the denormalized
        // `avg_rating` column. That column is maintained incrementally from the
        // `rating` JSON distribution and drifts out of sync when a review is
        // deleted/edited (the removed review lingers in the JSON), so it can
        // disagree with the real reviews the admin panel counts. One aggregate
        // query for the whole collection keeps this O(1), not N+1.
        if (! isset($context['rating_lookup'])) {
            $context['rating_lookup'] = self::liveRatingLookup(
                array_values(array_filter(array_map(
                    static fn ($it) => isset($it->id) ? (int) $it->id : null,
                    $items
                )))
            );
        }

        $result = [];
        foreach ($items as $item) {
            $result[] = self::fromOne($item, $context);
        }
        return $result;
    }

    /**
     * Batch-load AVG(rating)/COUNT(*) from the reviews table for the given item
     * ids and return a lookup closure: fn(int $id): ?array{rating,count}. Uses
     * the Review model so any global scopes match what the admin panel counts.
     */
    private static function liveRatingLookup(array $itemIds): callable
    {
        $ratings = [];
        if ($itemIds !== []) {
            $rows = \App\Models\Review::query()
                ->whereIn('item_id', $itemIds)
                ->groupBy('item_id')
                ->selectRaw('item_id, AVG(rating) as avg_rating, COUNT(*) as rating_count')
                ->get();
            foreach ($rows as $row) {
                $ratings[(int) $row->item_id] = [
                    'rating' => round((float) $row->avg_rating, 1),
                    'count'  => (int) $row->rating_count,
                ];
            }
        }

        return static fn (int $id): ?array => $ratings[$id] ?? null;
    }

    public static function fromOne(Item $item, array $context = []): array
    {
        $pricing = ItemPricing::compute($item);

        $moduleType = $item->module?->module_type;
        $isFood = $moduleType === 'food';
        $needsConfig = self::hasRequiredVariation($item)
            || (!$isFood && self::hasNonFoodVariations($item));

        $vegRaw            = $item->getAttributes()['veg'] ?? null;
        $moduleAllowsVeg   = (bool) config("module.{$moduleType}.veg_non_veg", false);
        $storeAllowsVeg    = (int) ($item->store?->veg ?? 0) === 1;
        $storeAllowsNonVeg = (int) ($item->store?->non_veg ?? 0) === 1;
        $isVeg    = $moduleAllowsVeg && $storeAllowsVeg    && $vegRaw !== null && (int) $vegRaw === 1;
        $isNonVeg = $moduleAllowsVeg && $storeAllowsNonVeg && $vegRaw !== null && (int) $vegRaw === 0;

        $tracksStock = (bool) config("module.{$moduleType}.stock", false);
        $stock       = $tracksStock ? (int) ($item->getAttributes()['stock'] ?? 0) : 0;

        $currency       = $context['currency']        ?? '$';
        $cartLookup     = $context['cart_lookup']     ?? null;
        $wishlistLookup = $context['wishlist_lookup'] ?? null;
        $ratingLookup   = $context['rating_lookup']   ?? null;

        $cartEntry = is_callable($cartLookup) ? $cartLookup($item->id) : null;

        // Prefer the live review aggregate (batched in fromCollection); fall back
        // to the stored column for an item with no reviews or a single-item call.
        $liveRating  = is_callable($ratingLookup) ? $ratingLookup($item->id) : null;
        $rating      = $liveRating['rating'] ?? round((float) ($item->avg_rating ?? 0), 1);
        $ratingCount = $liveRating['count']  ?? (int) ($item->rating_count ?? 0);


        $data = [
            'id'              => $item->id,
            'name'            => $item->name,
            'slug'            => $item->slug ?? null,
            'image'           => $item->image_full_url ?? null,
            'price'           => $pricing['price'],
            'oldPrice'        => $pricing['oldPrice'],
            'discountPercent' => $pricing['discountPercent'],
            'discountAmount'  => $pricing['discountAmount'],
            'discountType'    => $pricing['discountType'],
            'discountSource'  => $pricing['discountSource'],
            'rating'          => $rating,
            'ratingCount'     => $ratingCount,
            'currency'        => $currency,
            'isVeg'           => $isVeg,
            'isNonVeg'        => $isNonVeg,
            'inCart'          => $cartEntry !== null,
            'cartQty'         => (int) ($cartEntry['qty'] ?? 0),
            'isWishlist'      => is_callable($wishlistLookup) ? (bool) $wishlistLookup($item->id) : false,
            'moduleType'      => $moduleType,
            'needsConfig'     => $needsConfig,
            'stock'           => $stock,
            'tracksStock'     => $tracksStock,
        ];

        return ItemCardDTO::fromArray($data)->toArray();
    }

    private static function hasRequiredVariation(Item $item): bool
    {
        foreach (['food_variations', 'variations'] as $column) {
            foreach (self::decodeJsonField($item->getAttributes()[$column] ?? null) as $variation) {
                $required = $variation['required'] ?? 'off';
                if ($required === 'on' || $required === '1' || $required === 1 || $required === true) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function hasNonFoodVariations(Item $item): bool
    {
        $variations = self::decodeJsonField($item->getAttributes()['variations'] ?? null);
        return is_array($variations) && count($variations) > 0;
    }

    private static function decodeJsonField($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
