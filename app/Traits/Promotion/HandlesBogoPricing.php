<?php

namespace App\Traits\Promotion;

use App\Models\BogoOfferStore;
use App\Models\Item;

/**
 * What a bundle costs, and how that cost is spread over its cart rows.
 *
 * Every price here comes from the enrolment, never from the live menu: a bundle costs what it
 * cost when the store built it. Reading the menu instead would bill a customer something they
 * were never shown the moment an item's price moved.
 */
trait HandlesBogoPricing
{
    /**
     * The cart rows one bundle expands into.
     *
     * A bundle is N rows sharing a group id, not one row -- that is what lets the existing
     * per-line stock check, add-on handling and decrement path cover every member unchanged.
     * Free rows are priced zero; what the store gave away is recorded on the order line at
     * placement, because a cart has no expense yet.
     *
     * Note `variation` is singular here. The customer cart column is `variation` and is read by
     * Helpers::variation_price(); POS uses `variations` and pos_variation_price(), and the two
     * payload shapes are not interchangeable -- feeding one to the other throws.
     */
    protected function bogoCartRows(
        BogoOfferStore $enrollment,
        string $groupId,
        int $bundleQuantity,
        $userId,
        int $isGuest,
        int $moduleId
    ): array {
        $rows = [];

        foreach ($enrollment->items as $line) {
            $rows[] = [
                'user_id' => $userId,
                'is_guest' => $isGuest,
                'module_id' => $moduleId,
                'store_id' => $enrollment->store_id,
                'item_id' => $line->item_id,
                'item_type' => Item::class,
                'price' => $line->type === 'buy' ? (float) $line->price : 0,
                'quantity' => (int) $line->quantity * $bundleQuantity,
                'variation' => json_encode($line->variations ?: []),
                'add_on_ids' => json_encode($line->add_on_ids ?: []),
                'add_on_qtys' => json_encode($line->add_on_qtys ?: []),
                'bogo_offer_id' => $enrollment->bogo_offer_id,
                'bogo_group_id' => $groupId,
                'is_free_item' => $line->type === 'get' ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $rows;
    }

    /**
     * The frozen unit price of every enrolled line behind these cart rows, as
     * [bogo_group_id][identity] => price.
     *
     * $storeId covers the panel order editor, whose rows carry the offer but not the store,
     * since the whole order belongs to one.
     */
    protected function bogoFrozenUnitPrices($carts, ?int $storeId = null): array
    {
        $prices = [];

        $groups = collect($carts)
            ->filter(fn ($row) => data_get($row, 'bogo_group_id'))
            ->groupBy(fn ($row) => data_get($row, 'bogo_group_id'));

        foreach ($groups as $groupId => $rows) {
            $first = $rows->first();

            $enrollment = BogoOfferStore::where('bogo_offer_id', data_get($first, 'bogo_offer_id'))
                ->where('store_id', data_get($first, 'store_id') ?? $storeId)
                ->with('items')
                ->first();

            if (! $enrollment) {
                continue;
            }

            foreach ($enrollment->items as $line) {
                $price = (float) ($line->original_price ?? $line->price);

                // Keyed by what actually distinguishes one enrolled line from another. Side and
                // item alone collided whenever a bundle enrolled the same item twice at
                // different sizes: a Buy 2 Get 3 of one pizza in Small and Large priced every
                // buy line at whichever the loop wrote last, so the customer was billed the
                // dearer size twice for a mixed bundle, and the free lines were booked at one
                // price too.
                $prices[$groupId][$this->bogoLineIdentity(
                    $line->type, $line->item_id, $line->variations, $line->add_on_ids
                )] = $price;

                // The side-and-item key stays as a fallback for rows written before lines were
                // keyed by variation, so an order already in flight prices as it was placed.
                $prices[$groupId][$line->type.':'.$line->item_id] ??= $price;
            }
        }

        return $prices;
    }

    /**
     * How one cart line inside a bundle is priced: [unit price to charge, worth given away,
     * worth per unit].
     *
     * A free line costs nothing and its worth is reported separately, because the store carries
     * that cost and the payout has to book it. The third element is the same worth per unit for
     * the order line to store -- the item report attributes discounts per item and multiplies by
     * quantity itself, so it needs the unit figure rather than the line total.
     *
     * $fallback is what the caller worked out from the menu, used only when the enrolment can no
     * longer be found. The frozen price already includes variation and add-on cost, so a caller
     * must clear the line's add-on total rather than charging it again on top.
     */
    protected function bogoLinePrice(array $frozenPrices, $cart, float $fallback): array
    {
        $isFree = (bool) data_get($cart, 'is_free_item');
        $type = $isFree ? 'get' : 'buy';
        $group = $frozenPrices[data_get($cart, 'bogo_group_id')] ?? [];

        $identity = $this->bogoLineIdentity(
            $type,
            data_get($cart, 'item_id'),
            data_get($cart, 'variation') ?? data_get($cart, 'variations'),
            data_get($cart, 'add_on_ids')
        );

        $unit = $group[$identity] ?? $group[$type.':'.data_get($cart, 'item_id')] ?? $fallback;

        return $isFree
            ? [0.0, $unit * (int) data_get($cart, 'quantity', 1), $unit]
            : [$unit, 0.0, 0.0];
    }

    /**
     * What tells one line of a bundle from another.
     *
     * A bundle can enrol the same item more than once on the same side -- the same pizza in two
     * sizes, or with different add-ons -- and those lines have different prices. The item id
     * alone cannot tell them apart, so everything the enrolment froze goes into the key.
     *
     * The source keyed on variation OPTION IDS. 6amMart has none, so this keys on the variation
     * LABELS instead, normalised the same way: extracted, sorted, joined. Sorting matters more
     * than it looks -- the enrolment and the cart row written from it must produce the same
     * string, and neither guarantees an order.
     */
    protected function bogoLineIdentity(string $type, $itemId, $variations, $addOnIds): string
    {
        return $type.':'.(int) $itemId
            .':'.$this->bogoNormaliseLabels($variations)
            .':'.$this->bogoNormaliseIds($addOnIds);
    }

    /** Variation labels, whichever of the two shapes they arrive in, sorted and joined. */
    private function bogoNormaliseLabels($value): string
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return '';
        }

        $labels = [];

        foreach ($value as $group) {
            // Named food groups: {"name": "Size", "values": {"label": ["Half"]}}
            foreach ((array) data_get($group, 'values.label', []) as $label) {
                $labels[] = (string) $label;
            }

            // Flat non-food variations: {"type": "200gm"}
            if ($type = data_get($group, 'type')) {
                $labels[] = (string) $type;
            }
        }

        $labels = array_values(array_unique($labels));
        sort($labels);

        return implode(',', $labels);
    }

    private function bogoNormaliseIds($value): string
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return '';
        }

        $ids = array_values(array_unique(array_map('intval', array_filter($value, 'is_numeric'))));
        sort($ids);

        return implode(',', $ids);
    }

    /** What the bundle's items would have cost separately, at their frozen prices. */
    protected function bogoOriginalBundlePrice(BogoOfferStore $enrollment): float
    {
        $total = 0.0;

        foreach ($enrollment->items as $line) {
            $total += (float) ($line->original_price ?? $line->price) * (int) $line->quantity;
        }

        return round($total, config('round_up_to_digit', 2));
    }

    /**
     * What a bundle costs, and what a happy hour takes off it.
     *
     * A bundle is discounted by a happy hour and by nothing else. The bundle is already the
     * promotion, so a standing store discount would be discounting the same thing twice. A happy
     * hour is different in kind: the store's own scheduled window, covering the whole menu while
     * it is open, and borne in full by the store.
     *
     * Every surface reads this one method -- offer list, offer detail, store page and cart.
     * is_happy_hour flags that a happy hour is running, but the rate is never applied or quoted
     * here — matching how a plain item's own price/discount stays untouched everywhere but the
     * order total during a happy hour. The real cut is applied once, on the order total, by
     * BundleOrderService::distributeReduction() at order placement.
     */
    protected function bogoBundlePricing(BogoOfferStore $enrollment, ?float $happyHourPercentage = null): array
    {
        $digits = config('round_up_to_digit', 2);

        $bundlePrice = (float) ($enrollment->bundle_price ?? 0);
        $originalPrice = $this->bogoOriginalBundlePrice($enrollment);
        $isHappyHour = $happyHourPercentage > 0;

        return [
            'bundle_price' => round($bundlePrice, $digits),
            'original_price' => $originalPrice,
            'discount_percentage' => 0.0,
            'discount_amount' => 0.0,
            'final_price' => round($bundlePrice, $digits),
            'is_happy_hour' => $isHappyHour,
        ];
    }

    /**
     * Whether a store-wide discount may be taken off this line.
     *
     * A bundle is already the promotion, so a standing store discount is not taken off it as
     * well -- that would discount the same thing twice and bill someone for a give-away the
     * offer had already paid for. A happy hour is different in kind and does reach a bundle.
     */
    protected function bogoLineIsDiscountable($detail, bool $isHappyHour): bool
    {
        if (! data_get($detail, 'bogo_group_id')) {
            return true;
        }

        return $isHappyHour;
    }
}
