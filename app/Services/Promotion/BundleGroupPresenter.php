<?php

namespace App\Services\Promotion;

use App\Models\Bundle;
use App\Models\Item;
use App\Services\Item\AddonService;
use App\Traits\Promotion\HandlesBundleCart;
use Illuminate\Support\Collection;

class BundleGroupPresenter
{
    use HandlesBundleCart;

    public function present(
        Collection $carts,
        $userId,
        int $isGuest,
        ?float $happyHourPercentage = null,
        bool $storePromotionRunning = false
    ): array {
        $entries = [];

        foreach ($carts as $cart) {
            if (! $cart->bundle_group_id) {
                $cart->bundle_details = $cart->bundle_details ?? null;
                $entries[] = $cart;
            }
        }

        $bundles = app(BundleService::class)->findManyForCart(
            $carts->pluck('bundle_id')->all(),
            $carts->filter(fn ($cart) => $cart->item instanceof Item)->pluck('item', 'item_id')
        );

        $this->primeActiveItems(
            $bundles->flatMap(fn ($bundle) => $bundle->items->pluck('item_id'))->all()
        );

        $digits = (int) config('round_up_to_digit', 2);

        // Resolved once for every line of every bundle on the page, not per line: each member
        // carries its own add-on ids and a cart routinely holds several bundles (rule 11).
        $addOns = $this->addOnsByLine($bundles);

        foreach ($carts->filter(fn ($c) => $c->bundle_group_id)->groupBy('bundle_group_id') as $groupId => $rows) {
            $first = $rows->first();
            $bundle = $bundles[$first->bundle_id] ?? null;

            $quantity = $this->bundleGroupQuantity($rows, $bundle);

            $pricing = $bundle
                ? $this->bundlePricing($bundle, $happyHourPercentage, $storePromotionRunning)
                : null;

            $reason = $bundle
                ? $this->bundleUnavailableReason($bundle, $quantity, null, $rows)
                : translate('messages.The added bundle is unavailable.');

            $entry = clone $first;

            foreach (['id', 'item_id', 'item_type', 'reel_id', 'variation',
                'add_on_ids', 'add_on_qtys', 'is_free_item'] as $field) {
                $entry->{$field} = null;
            }

            $entry->setRelation('item', null);

            $entry->price = round((float) ($pricing['final_price'] ?? 0), $digits);
            $entry->quantity = $quantity;
            $entry->bogo_details = null;
            $entry->bundle_details = [
                'bundle_group_id' => $groupId,
                'bundle_id' => $bundle?->id,
                'name' => $bundle?->name,
                'image_full_url' => $bundle?->image_full_url,
                'start_date' => $bundle?->start_date?->format('Y-m-d H:i:s'),
                'end_date' => $bundle?->end_date?->format('Y-m-d H:i:s'),
                'quantity' => $quantity,
                'base_price' => $pricing['base_price'] ?? 0,
                'bundle_price' => $pricing['bundle_price'] ?? 0,
                'discount_percentage' => $pricing['discount_percentage'] ?? 0,
                'discount_amount' => $pricing['discount_amount'] ?? 0,
                'final_price' => $pricing['final_price'] ?? 0,
                'is_happy_hour' => $pricing['is_happy_hour'] ?? false,
                'total_final_price' => round((float) ($pricing['final_price'] ?? 0) * $quantity, $digits),
                'total_discount_amount' => round((float) ($pricing['discount_amount'] ?? 0) * $quantity, $digits),
                'is_available' => $reason === null,
                'unavailable_reason' => $reason,
                'items' => $bundle?->items->map(fn ($line) => [
                    'item_id' => $line->item_id,
                    'name' => $line->item_name,
                    'image_full_url' => $line->item_image_full_url,
                    'unit_price' => (float) $line->unit_price,
                    'variation_summary' => implode(', ', $line->variationLabels()),
                    // A bundle member's add-ons were the one thing this payload dropped: the
                    // group entry itself nulls `add_on_ids` (a group is not one item) and the
                    // members were mapped down to five keys, so a cart holding a bundle could
                    // not say what was on its lines -- while a BOGO group ships its members as
                    // whole cart rows and shows theirs. Raw pair plus the resolved list, in the
                    // same `addons` shape CartResource gives an ordinary row.
                    'add_on_ids' => $line->add_on_ids ?? [],
                    'add_on_qtys' => $line->add_on_qtys ?? [],
                    'addons' => $addOns[$line->id] ?? [],
                ])->values()->all() ?? [],
            ];

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Every bundle line's add-ons, resolved, keyed by line id.
     *
     * Shaped exactly as CartService::attachSelections() leaves `selected_addons` on a cart row
     * -- what CartResource publishes as `addons` -- so a client renders a bundle member's
     * add-ons with the code it already uses for an ordinary line.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function addOnsByLine(Collection $bundles): array
    {
        $lines = $bundles->flatMap(fn ($bundle) => $bundle->items);

        $ids = $lines
            ->flatMap(fn ($line) => $line->add_on_ids ?? [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $addOns = $ids ? app(AddonService::class)->getByIdsWithTaxes($ids) : collect();

        $resolved = [];

        foreach ($lines as $line) {
            $qtys = array_values($line->add_on_qtys ?? []);
            $rows = [];

            foreach (array_values($line->add_on_ids ?? []) as $index => $addOnId) {
                $addOn = $addOns[(int) $addOnId] ?? null;

                if (! $addOn) {
                    continue;
                }

                $rows[] = [
                    'id' => (int) $addOn->id,
                    'name' => $addOn->name,
                    'price' => (float) $addOn->price,
                    'store_id' => (int) $addOn->store_id,
                    'status' => (int) $addOn->status,
                    'addon_category_id' => $addOn->addon_category_id,
                    'tax_ids' => $addOn->taxVats->pluck('tax_id')->values()->all(),
                    'isChecked' => true,
                    'quantity' => (int) ($qtys[$index] ?? 1),
                ];
            }

            // A line with no add-ons still gets a key, so a caller can index by line id without
            // checking first -- the same contract AddOnLabels::forLines() keeps.
            $resolved[$line->id] = $rows;
        }

        return $resolved;
    }

    public function unavailableReasonFor(Bundle $bundle, int $quantity): ?string
    {
        return $this->bundleUnavailableReason($bundle, $quantity);
    }

    public function groupQuantityFor(Collection $rows, ?Bundle $bundle): int
    {
        return $this->bundleGroupQuantity($rows, $bundle);
    }

    /**
     * What a bundle costs, and what it may say it is saving the customer.
     *
     * While a store-wide promotion is open -- a happy hour or the vendor's own standing store
     * discount -- the bundle quotes no saving at all: discount_percentage and discount_amount go
     * to 0 and the price stays the bundle's own, never re-cut by the window's rate. The store-wide
     * rate outranks the bundle's own (StoreDiscountResolver's precedence table), so quoting the
     * bundle's percentage beside it would read as two discounts on one line, and re-cutting the
     * price by the window would advertise a total no other surface agrees with. Same treatment
     * bogoBundlePricing() gives a BOGO and ItemResource gives a plain item: the real cut is
     * applied once, on the order total, by BundleOrderService::distributeReduction() at placement.
     *
     * is_happy_hour still reports a happy hour specifically, because it names the window rather
     * than the saving -- a plain store discount silences the percentage without lighting it.
     *
     * Every surface reads this one method: bundle list, home, detail, store bundles and the cart.
     */
    public function bundlePricing(
        Bundle $bundle,
        ?float $happyHourPercentage = null,
        bool $storePromotionRunning = false
    ): array {
        $digits = (int) config('round_up_to_digit', 2);

        $base = round((float) $bundle->base_price, $digits);
        $final = max(0.0, round((float) $bundle->discounted_price, $digits));

        $isHappyHour = $happyHourPercentage > 0;
        $quotesSaving = ! $isHappyHour && ! $storePromotionRunning;

        return [
            'base_price'          => $base,
            'bundle_price'        => $final,
            'discount_percentage' => $quotesSaving ? (float) $bundle->discount_percentage : 0.0,
            'discount_amount'     => $quotesSaving ? round(max(0, $base - $final), $digits) : 0.0,
            'final_price'         => $final,
            'is_happy_hour'       => $isHappyHour,
        ];
    }
}
