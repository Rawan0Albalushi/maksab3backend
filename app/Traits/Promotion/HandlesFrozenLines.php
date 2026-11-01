<?php

namespace App\Traits\Promotion;

use App\CentralLogics\Helpers;
use App\Models\AddOn;
use App\Models\Item;
use App\Scopes\StoreScope;
use App\Scopes\ZoneScope;

trait HandlesFrozenLines
{
    private array $frozenActiveItems = [];

    protected function primeActiveItems(array $itemIds): void
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($itemIds))));

        $this->frozenActiveItems = [];

        if (! $ids) {
            return;
        }

        $active = $this->listableItemQuery()->whereIn('id', $ids)->pluck('id')->all();

        foreach ($ids as $id) {
            $this->frozenActiveItems[$id] = in_array($id, $active, true);
        }
    }

    protected function forgetActiveItems(): void
    {
        $this->frozenActiveItems = [];
    }

    protected function itemIsActive($item): bool
    {
        $id = (int) $item->getKey();

        if (array_key_exists($id, $this->frozenActiveItems)) {
            return $this->frozenActiveItems[$id];
        }

        return $this->listableItemQuery()->whereKey($id)->exists();
    }

    protected function usesFoodVariations($item): bool
    {
        return ($item->module?->module_type ?? null) === 'food';
    }

    protected function lineUnavailableReason($item, $line, int $quantity, ?int $perBundleOverride = null): ?string
    {
        if (! $this->itemIsActive($item)) {
            return $item->name.' '.translate('messages.Is currently unavailable');
        }

        return $this->outsideServingHoursReason($item)
            ?? $this->variationMissingReason($item, $line)
            ?? $this->stockShortfallReason($item, $line, $quantity, $perBundleOverride);
    }

    /**
     * The stock a line draws on, so two lines wanting the same stock are counted together.
     *
     * A food variation carries no stock of its own -- availableStock() reads the item's -- so
     * every line of that item shares one pool. Everywhere else the chosen combination holds the
     * stock, so the pool is the combination.
     */
    protected function stockPoolKey($item, $line): string
    {
        $id = (int) $item->getKey();

        return $this->usesFoodVariations($item)
            ? $id.'|item'
            : $id.'|'.($this->combinationKey($line->variations) ?? '');
    }

    protected function linesUnavailableReason(iterable $lines, int $quantity, string $missingKey): ?string
    {
        $resolved = [];

        foreach ($lines as $line) {
            $item = $line->relationLoaded('item') ? $line->item : Item::with('module')->find($line->item_id);

            if (! $item) {
                return $line->item_name.' '.translate($missingKey);
            }

            $resolved[] = [$item, $line];
        }

        // Buy and get routinely name the same item AND the same variant -- that is what a
        // buy-one-get-one on a single product is. Asked a line at a time, each half only ever
        // claimed its own quantity, so one copy of a "buy 1 get 1" needing two units of a variant
        // cleared a stock of one, and the cap reported back ("only N of this offer can be
        // ordered") was worked out from one half alone and came out double what the stock could
        // really cover. Demand is summed per stock pool here and tested once against it.
        $demand = [];

        foreach ($resolved as [$item, $line]) {
            $key = $this->stockPoolKey($item, $line);
            $demand[$key] = ($demand[$key] ?? 0) + max(1, (int) $line->quantity);
        }

        foreach ($resolved as [$item, $line]) {
            $reason = $this->lineUnavailableReason(
                $item, $line, $quantity, $demand[$this->stockPoolKey($item, $line)] ?? null
            );

            if ($reason) {
                return $reason;
            }
        }

        return null;
    }

    protected function outsideServingHoursReason($item): ?string
    {
        $starts = $item->getRawOriginal('available_time_starts');
        $ends = $item->getRawOriginal('available_time_ends');

        if (! $starts || ! $ends) {
            return null;
        }

        $now = now()->format('H:i:s');

        if ($starts <= $now && $ends >= $now) {
            return null;
        }

        return $item->name.' '.translate('messages.Is not available at this time')
            .' ('.substr((string) $starts, 0, 5).' - '.substr((string) $ends, 0, 5).')';
    }

    protected function variationMissingReason($item, $line): ?string
    {
        $chosen = Helpers::decodeJsonToArray($line->variations);

        if (empty($chosen)) {
            return null;
        }

        return $this->usesFoodVariations($item)
            ? $this->foodVariationReason($item, $chosen)
            : $this->combinationReason($item, $chosen);
    }

    /**
     * @param  int|null  $perBundleOverride  units of this line's stock pool one copy of the offer
     *                                       consumes, when other lines draw on the same pool too
     */
    protected function stockShortfallReason($item, $line, int $quantity, ?int $perBundleOverride = null): ?string
    {
        $available = $this->availableStock($item, $line);

        if ($available === null) {
            return null;
        }

        $perBundle = max(1, $perBundleOverride ?? (int) $line->quantity);
        $needed = $perBundle * max(1, $quantity);

        if ($available >= $needed) {
            return null;
        }

        $possible = intdiv($available, $perBundle);

        if ($possible < 1) {
            return trim($item->name.' '.translate('messages.Is out of stock'));
        }

        return translate('messages.Only').' '.$possible.' '.translate('messages.Of this offer can be ordered')
            .' - '.$item->name.' '.translate('messages.Has only').' '.$available.' '
            .translate('messages.Left in stock');
    }

    protected function availableStock($item, $line): ?int
    {
        $moduleType = $item->module?->module_type ?? null;

        if (! $moduleType || ! config('module.'.$moduleType.'.stock')) {
            return null;
        }

        $itemVariations = Helpers::decodeJsonToArray($item->variations);
        $chosen = Helpers::decodeJsonToArray($line->variations);

        if (! $this->usesFoodVariations($item) && $itemVariations && $chosen) {
            return (int) ($this->combinationFor($item, $chosen)['stock'] ?? 0);
        }

        return (int) ($item->stock ?? 0);
    }

    protected function combinationKey(mixed $chosen): ?string
    {
        $parts = [];

        foreach (Helpers::decodeJsonToArray($chosen) as $entry) {
            if (isset($entry['type'])) {
                $parts[] = (string) $entry['type'];

                continue;
            }

            foreach ((array) data_get($entry, 'values.label', []) as $label) {
                $parts[] = (string) $label;
            }
        }

        $parts = array_values(array_filter($parts, 'strlen'));

        return $parts ? implode('-', $parts) : null;
    }

    protected function combinationFor($item, mixed $chosen): ?array
    {
        $key = $this->combinationKey($chosen);

        if ($key === null) {
            return null;
        }

        foreach (Helpers::decodeJsonToArray($item->variations ?? null) as $combination) {
            if (($combination['type'] ?? null) === $key) {
                return $combination;
            }
        }

        return null;
    }

    protected function unitPrice(Item $item, ?array $variations, ?array $addOnIds, ?array $addOnQtys): float
    {
        $price = (float) $item->price;

        if (! empty($variations)) {
            if ($this->usesFoodVariations($item)) {
                $price += (float) Helpers::food_variation_price(
                    Helpers::decodeJsonToArray($item->food_variations), $variations
                );
            } elseif (Helpers::decodeJsonToArray($item->variations)) {
                $matched = (float) ($this->combinationFor($item, $variations)['price'] ?? 0);

                $price = $matched > 0 ? $matched : $price;
            }
        }

        if (! empty($addOnIds)) {
            $addOnIds = array_values($addOnIds);
            $addOnQtys = array_values($addOnQtys ?? []);

            $offered = Helpers::decodeJsonToArray($item->add_ons);

            foreach (AddOn::whereIn('id', $addOnIds)->whereIn('id', $offered)->get() as $addOn) {
                $index = array_search($addOn->id, $addOnIds);
                $price += (float) $addOn->price * (int) ($index === false ? 1 : ($addOnQtys[$index] ?? 1));
            }
        }

        return $price;
    }

    protected function serviceUnitPrice($service, ?array $variations): float
    {
        $chosen = $this->serviceVariant($service, $variations);

        return $chosen
            ? (float) ($chosen['price'] ?? 0)
            : (float) $service->base_price;
    }

    protected function serviceVariant($service, ?array $variations): ?array
    {
        $key = $this->serviceVariantKey($variations);

        if ($key === null) {
            return null;
        }

        foreach (Helpers::decodeJsonToArray($service->variations) as $variant) {
            if (($variant['variant_key'] ?? null) === $key) {
                return $variant;
            }
        }

        return null;
    }

    protected function serviceVariantKey(?array $variations): ?string
    {
        $first = $variations[0] ?? $variations ?? null;

        $key = is_array($first) ? ($first['variant_key'] ?? null) : null;

        return is_scalar($key) && trim((string) $key) !== '' ? trim((string) $key) : null;
    }

    private function listableItemQuery()
    {
        return Item::withoutGlobalScope(StoreScope::class)
            ->withoutGlobalScope(ZoneScope::class)
            ->active();
    }

    private function combinationReason($item, array $chosen): ?string
    {
        $key = $this->combinationKey($chosen);

        if ($key === null || $this->combinationFor($item, $chosen)) {
            return null;
        }

        return $item->name.' - '.$key.' : '
            .translate('messages.The selected variation is no longer available');
    }

    private function foodVariationReason($item, array $chosen): ?string
    {
        $foodGroups = Helpers::decodeJsonToArray($item->food_variations);

        foreach ($chosen as $variation) {
            if (isset($variation['type'])) {
                return $item->name.' : '.translate('The selected variation is no longer available');
            }

            $group = null;

            foreach ($foodGroups as $candidate) {
                if (($candidate['name'] ?? null) === ($variation['name'] ?? null)) {
                    $group = $candidate;
                    break;
                }
            }

            $labels = array_column($group['values'] ?? [], 'label');

            foreach ((array) data_get($variation, 'values.label', []) as $option) {
                if (! in_array($option, $labels, true)) {
                    return $item->name.' - '.$option.' : '
                        .translate('messages.The selected variation is no longer available');
                }
            }
        }

        return null;
    }
}
