<?php

namespace App\Http\Resources\Vendor\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * One line of a store's frozen BOGO selection, as the app shows it back.
 *
 * Described from the snapshot rather than the live menu on purpose: the copy has to outlive edits
 * to and deletion of the item, so a store reviewing what it submitted sees what it submitted.
 *
 * The two prices are not the same question. `price` is what the line costs INSIDE the bundle -- a
 * free line is 0 -- while `original_price` is what the item was worth when the bundle was built,
 * which is what the store actually hands over.
 */
class BogoEnrollmentItemResource extends BaseResource
{
    /**
     * `$addOnLines` is "Extra Cheese (2)" per enrolment-line id, from `AddOnLabels::forLines()`.
     * Passed in for the reason every sibling resource takes it: a frozen line holds add-on IDS,
     * and resolving names is a query a resource may not run (rule 3).
     */
    public function __construct($resource, private readonly array $addOnLines = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'item_id' => (int) $this->resource->item_id,
            'item_name' => $this->resource->item_name,
            'item_image_full_url' => $this->resource->item_image_full_url,
            'quantity' => (int) $this->resource->quantity,
            'price' => (float) $this->resource->price,
            'original_price' => (float) ($this->resource->original_price ?? $this->resource->price),
            'variations' => $this->resource->variations ?: [],
            'variation_summary' => implode(', ', $this->resource->variationLabels()),
            // The add-on counterpart of `variation_summary`, matching the other three payloads.
            'add_on_summary' => implode(', ', $this->addOnLines[$this->resource->id] ?? []),
            'add_on_ids' => $this->resource->add_on_ids ?: [],
            'add_on_qtys' => $this->resource->add_on_qtys ?: [],
        ]);
    }
}
