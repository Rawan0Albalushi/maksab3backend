<?php

namespace App\Http\Resources\Common\Item;

class CartProductResource extends ProductResource
{
    public function __construct(
        mixed $resource,
        private readonly array $selectedVariation = [],
        private readonly array $selectedAddons = [],
        private readonly array $selectedAddonQuantity = []
    ) {
        parent::__construct($resource);
    }

    protected function cartFields(array $data): array
    {
        $quantities = [];

        foreach ($this->selectedAddons as $index => $addonId) {
            $quantities[$addonId] = $this->selectedAddonQuantity[$index] ?? 1;
        }

        $addons = collect($this->resource->resolved_add_ons ?? [])
            ->sortBy('id')
            ->map(function ($addOn) use ($quantities) {
                $addOn = clone $addOn;
                $isChecked = in_array($addOn->id, $this->selectedAddons);
                $addOn->tax_ids = $addOn->taxVats->pluck('tax_id')->all();
                $addOn->isChecked = $isChecked;
                $addOn->quantity = $isChecked ? $quantities[$addOn->id] : 0;
                $addOn->unsetRelation('taxVats');

                return $addOn->toArray();
            })
            ->values()
            ->all();

        return [
            'addons' => $addons,
            'add_ons' => $this->resource->getRawOriginal('add_ons'),
            'store_slug' => $this->resource->store?->slug,
            'food_variations' => $this->selectedFoodVariations($data),
        ];
    }

    protected function relationRows(): array
    {
        $relations = parent::relationRows();

        if ($this->resource->module?->module_type !== 'ecommerce') {
            unset($relations['seo_data']);
        }

        return $relations;
    }

    private function selectedFoodVariations(array $data): array
    {
        $foodVariations = is_array($data['food_variations']) ? $data['food_variations'] : [];

        if (($data['module_type'] ?? null) !== 'food') {
            return $foodVariations;
        }

        foreach ($this->selectedVariation as $selected) {
            $labels = $selected['values']['label'] ?? [];

            foreach ($foodVariations as &$variation) {
                if (($selected['name'] ?? null) !== ($variation['name'] ?? null)) {
                    continue;
                }

                foreach ($variation['values'] as &$value) {
                    $value['isSelected'] = isset($value['label']) && in_array($value['label'], $labels);
                }
                unset($value);
            }
            unset($variation);
        }

        return $foodVariations;
    }
}
