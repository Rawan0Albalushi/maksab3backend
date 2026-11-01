<?php

namespace App\Builder\Support;

use App\CentralLogics\Helpers;
use App\Models\Item;

final class ItemPricing
{
    /**
     * What the storefront shows for one item.
     *
     * A store-wide rate -- a happy hour or the vendor's own standing discount -- is NOT shown on
     * the item. It comes off the whole basket at checkout and carries its own minimum spend, so it
     * is not a promise a single item can keep: striking a price out here and then declining the
     * reduction because the basket came in under the minimum is worse than never showing it. The
     * item therefore reads at base price and the reduction appears once, on checkout.
     *
     * This is the same rule the customer API follows (ItemResource) and the one StackFood has
     * always followed. The storefront is a customer surface, so it cannot differ: the same item at
     * the same moment would otherwise carry a strikethrough on the web and none in the app.
     *
     * Two things still discount here, and both are genuinely per-item:
     *   - a FLASH SALE, which replaces the price outright rather than folding a rate in
     *   - the item's OWN discount, whenever no store-wide rate is in force
     *
     * `storeWideRate` carries the rate for the store's banner. It describes the STORE, not this
     * item, and rendering it as a price reduction reintroduces exactly what this prevents.
     */
    public static function compute(Item $item, ?float $basePrice = null): array
    {
        $base = $basePrice ?? (float) ($item->price ?? 0);
        $store = $item->store ?? null;
        $calc = Helpers::product_discount_calculate($item, $base, $store, true);

        $source = $calc['discount_type'] ?? null;
        $isFlashSale = $source === 'flash_sale';

        // Asked of the resolver rather than read off $source: the rate can be in force and still
        // lose the max() to a larger item discount, and it is suppressed either way.
        $storeWide = $isFlashSale ? null : Helpers::get_store_discount($store);
        $suppressed = $storeWide !== null;

        $discountAmount = $suppressed ? 0.0 : (float) ($calc['discount_amount'] ?? 0);
        $final = max(0.0, $base - $discountAmount);
        $percentOff = $base > 0
            ? (int) round((1 - ($final / $base)) * 100)
            : 0;

        return [
            'price'           => $final,
            'oldPrice'        => $base,
            'discountAmount'  => $discountAmount,
            'discountPercent' => $percentOff,
            'discountType'    => $calc['original_discount_type'] ?? 'percent',
            'discountSource'  => $suppressed ? null : $source,
            'isFlashSale'     => $isFlashSale,
            // False while suppressed: the storefront uses this to badge the ITEM, and the whole
            // point is that the item carries no such badge right now.
            'isStoreDiscount' => ! $suppressed && $source === 'store_discount',
            // The store's rate, for its banner. Null when none is running.
            'storeWideRate'   => $storeWide ? (float) $storeWide['discount'] : null,
            'storeWideSource' => $storeWide['source'] ?? null,
        ];
    }
}
