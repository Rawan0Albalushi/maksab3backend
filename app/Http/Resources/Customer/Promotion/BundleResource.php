<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Models\Bundle;
use Illuminate\Http\Resources\Json\JsonResource;

class BundleResource extends JsonResource
{
    /**
     * `$addOnLines` is "Extra Cheese (2)" per bundle_item id, from
     * `BundleService::addOnLinesFor()`. Passed in rather than resolved here because a bundle line
     * freezes add-on IDS and the names need a query — which a resource never runs (rule 3), and
     * which has to be batched across the page anyway.
     */
    public function __construct(
        Bundle $resource,
        private readonly array $pricing = [],
        private readonly ?string $unavailableReason = null,
        private readonly bool $withItems = false,
        private readonly array $addOnLines = [],
    ) {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        $bundle = $this->resource;

        return [
            'id' => $bundle->id,
            'name' => $bundle->name,
            'description' => $bundle->description,
            'slug' => $bundle->slug,
            'image_full_url' => $bundle->image_full_url,
            'store_id' => $bundle->store_id,
            'store_name' => $bundle->store?->name,
            'store_logo_full_url' => $bundle->store?->logo_full_url,
            'module_id' => $bundle->module_id,
            'start_date' => $bundle->start_date?->format('Y-m-d H:i:s'),
            'end_date' => $bundle->end_date?->format('Y-m-d H:i:s'),
            'item_count' => (int) ($bundle->items_count ?? $bundle->items->count()),
            'base_price' => (float) ($this->pricing['base_price'] ?? $bundle->base_price),
            'bundle_price' => (float) ($this->pricing['bundle_price'] ?? $bundle->discounted_price),
            'discount_percentage' => (float) ($this->pricing['discount_percentage'] ?? $bundle->discount_percentage),
            'discount_amount' => (float) ($this->pricing['discount_amount'] ?? 0),
            'final_price' => (float) ($this->pricing['final_price'] ?? $bundle->discounted_price),
            'is_happy_hour' => (bool) ($this->pricing['is_happy_hour'] ?? false),
            'is_available' => $this->unavailableReason === null,
            'unavailable_reason' => $this->unavailableReason,
            'items' => $this->withItems
                ? $bundle->items->map(fn ($line) => [
                    'item_id' => $line->item_id,
                    'service_id' => $line->service_id,
                    'is_service' => $line->isService(),
                    'name' => $line->item_name,
                    'image_full_url' => $line->item_image_full_url,
                    'unit_price' => (float) $line->unit_price,
                    'quantity' => (int) $line->quantity,
                    'variation_summary' => implode(', ', $line->variationLabels()),
                    'variations' => $line->variations ?? [],
                    // The add-on counterpart of `variation_summary`, in the same shape: one
                    // ready-to-print string. The two id/qty arrays below stay as they were (N9)
                    // and remain the machine-readable form — they just no longer force a client
                    // to look every name up before it can render the line.
                    'add_on_summary' => implode(', ', $this->addOnLines[$line->id] ?? []),
                    'add_on_ids' => $line->add_on_ids ?? [],
                    'add_on_qtys' => $line->add_on_qtys ?? [],
                ])->values()->all()
                : null,
        ];
    }

    public function render(): array
    {
        return $this->toArray(request());
    }
}
