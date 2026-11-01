<?php

namespace App\Http\Resources\Vendor\Promotion;

use App\Models\Bundle;
use Illuminate\Http\Resources\Json\JsonResource;

class BundleResource extends JsonResource
{
    /**
     * `$addOnLines` is "Extra Cheese (2)" per bundle_item id, from `AddOnLabels::forParents()`.
     * Passed in because a line freezes add-on IDS and resolving the names is a query, which a
     * resource never runs (rule 3) and which has to be batched across the page anyway.
     */
    public function __construct(
        Bundle $resource,
        private readonly bool $withItems = false,
        private readonly array $addOnLines = [],
        private readonly bool $withTranslations = false,
    ) {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        $bundle = $this->resource;

        $data = [
            'id' => $bundle->id,
            'name' => $bundle->name,
            'description' => $bundle->description,
            'image_full_url' => $bundle->image_full_url,
            'start_date' => $bundle->start_date?->format('Y-m-d H:i:s'),
            'end_date' => $bundle->end_date?->format('Y-m-d H:i:s'),
            'base_price' => (float) $bundle->base_price,
            'discount_percentage' => (float) $bundle->discount_percentage,
            'discounted_price' => (float) $bundle->discounted_price,
            'status' => (int) $bundle->status,
            'created_by' => $bundle->created_by,
            'visibility_status' => $bundle->visibilityStatus(),
            'item_count' => (int) ($bundle->items_count ?? $bundle->items->count()),
            'items' => $this->withItems
                ? $bundle->items->map(fn ($line) => [
                    'item_id' => $line->item_id,
                    'service_id' => $line->service_id,
                    'is_service' => $line->isService(),
                    'name' => $line->item_name,
                    'image_full_url' => $line->item_image_full_url,
                    'unit_price' => (float) $line->unit_price,
                    'variation_summary' => implode(', ', $line->variationLabels()),
                    'variations' => $line->variations ?? [],
                    // The add-on counterpart of `variation_summary`, same shape, same contract as
                    // the customer resource. The id/qty arrays below stay as they were (N9).
                    'add_on_summary' => implode(', ', $this->addOnLines[$line->id] ?? []),
                    'add_on_ids' => $line->add_on_ids ?? [],
                    'add_on_qtys' => $line->add_on_qtys ?? [],
                ])->values()->all()
                : null,
        ];

        if ($this->withTranslations) {
            $data['translations'] = $bundle->relationLoaded('translations')
                ? $bundle->getRelation('translations')->values()
                : collect();
        }

        return $data;
    }

    public function render(): array
    {
        return $this->toArray(request());
    }
}
