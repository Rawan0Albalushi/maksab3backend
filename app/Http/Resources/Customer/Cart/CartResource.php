<?php

namespace App\Http\Resources\Customer\Cart;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use App\Http\Resources\Common\Item\ItemResource;
use App\Http\Resources\Customer\Promotion\ItemCampaignResource;
use App\Models\ItemCampaign;
use Illuminate\Http\Request;

class CartResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'module_id' => (int) $this->resource->module_id,
            'store_id' => $this->resource->store_id,
            'price' => (float) $this->resource->price,
            'quantity' => (int) $this->resource->quantity,
            'variation' => $this->resource->getAttribute('selected_variation') ?? [],
            'add_on_ids' => $this->resource->getAttribute('selected_addon_ids') ?? [],
            'add_on_qtys' => $this->resource->getAttribute('selected_addon_qtys') ?? [],
            'item' => $this->item($request),
            // Present on every entry and null for an ordinary item. A folded BOGO bundle carries
            // its offer, its members and its pricing here, while `price` and `quantity` above are
            // restated as the bundle's -- so a client that ignores this key still totals the cart
            // correctly. Built by BogoGroupPresenter; nothing is resolved in this resource.
            'bogo_details' => $this->resource->getAttribute('bogo_details'),
            'bundle_details' => $this->resource->getAttribute('bundle_details'),
        ]);
    }

    private function item(Request $request): ?array
    {
        $item = $this->resource->item;

        if (! $item) {
            return null;
        }

        $base = $this->resource->item_type === ItemCampaign::class
            ? new ItemCampaignResource($item)
            : new ItemResource($item);

        return array_merge($base->toArray($request), $base->detailFields(), [
            'addons' => $this->resource->getAttribute('selected_addons') ?? [],
            'food_variations' => $this->markSelectedVariations($item),
        ]);
    }

    private function markSelectedVariations(mixed $item): array
    {
        $variations = Helpers::decodeJsonToArray($item->food_variations);
        $selected = $this->resource->getAttribute('selected_variation') ?? [];

        if (empty($variations) || empty($selected)) {
            return $variations;
        }

        foreach ($variations as &$variation) {
            $labels = $this->buildLabels($selected, $variation['name'] ?? null);

            foreach (($variation['values'] ?? []) as &$value) {
                $value['isSelected'] = isset($value['label']) && in_array($value['label'], $labels, true);
            }
            unset($value);
        }
        unset($variation);

        return $variations;
    }

    private function buildLabels(array $selected, ?string $name): array
    {
        foreach ($selected as $entry) {
            if (($entry['name'] ?? null) === $name) {
                return (array) ($entry['values']['label'] ?? []);
            }
        }

        return [];
    }
}
