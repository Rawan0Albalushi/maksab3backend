<?php

namespace App\Http\Resources\Vendor\Promotion;

use App\Http\Resources\BaseResource;
use App\Models\BogoOffer;
use Illuminate\Http\Request;

/**
 * One offer as the store app needs it: the offer, whether this store may join, its own enrolment,
 * and what it is allowed to do next.
 *
 * Every derived key here -- eligibility, visibility, the action set, the labels -- is attached by
 * BogoOfferVendorService::primeOfferRows(), because working any of them out reads the enrolled
 * items and the store's schedule. A resource may not query.
 *
 * The distinction the payload exists to draw: `enrollment_state` is where the request stands with
 * the ADMIN, `visibility_status` is what a CUSTOMER can see today. "Approved" is not "running" --
 * a sold-out item, a switched-off category, a closed store or a filled cap each hide an approved
 * bundle while they last, and the app had no way to tell a store why.
 */
class BogoOfferResource extends BaseResource
{
    /**
     * `$addOnLines` is "Extra Cheese (2)" per enrolment-line id, from `AddOnLabels::forLines()`.
     * Resolved by the caller and threaded through, never looked up here — the class note above
     * says a resource may not query, and that holds for add-on names as much as for eligibility.
     */
    public function __construct(
        $resource,
        private readonly bool $withItems = false,
        private readonly array $addOnLines = [],
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $visibility = $this->resource->visibility ?? [];

        $payload = array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'slug' => $this->resource->slug,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'image_full_url' => $this->resource->image_full_url,
            'buy_qty' => (int) $this->resource->buy_qty,
            'get_qty' => (int) $this->resource->get_qty,
            'usage_limit_total' => $this->resource->usage_limit_total,
            'usage_limit_per_customer' => $this->resource->usage_limit_per_customer,
            'total_uses' => (int) $this->resource->total_uses,
            'start_date' => $this->resource->start_date?->format('Y-m-d'),
            'end_date' => $this->resource->end_date?->format('Y-m-d'),
            'status' => (int) $this->resource->status,

            // A finished offer, kept on the list because this store is on it. The panel shows
            // nothing else on such a row -- no state, no action -- so the app has the same single
            // fact to render it from.
            'is_expired' => (bool) ($this->resource->has_ended ?? false),
            'is_eligible' => (bool) ($this->resource->is_eligible ?? false),
            'ineligible_reason' => $this->resource->ineligible_reason,

            'enrollment_state' => $this->resource->state,
            'enrollment_state_label' => $this->resource->state_label,
            // Who said no and why, as one line -- a denial the store issued itself must not read
            // as the admin's.
            'rejection_note' => $this->resource->rejection_note,

            'visibility_status' => $visibility['status'] ?? null,
            'visibility_label' => $visibility['label'] ?? null,
            'visibility_reasons' => $visibility['reasons'] ?? [],

            'enrollment' => $this->enrollment(),
            'actions' => $this->resource->actions ?? [],
            // Why the actions are off when it is not about eligibility: a denied request may only
            // be reworked, and an ended offer may not be touched at all. Null while it is live.
            'actions_locked_reason' => $this->resource->locked_reason,
        ]);

        // Omitted rather than sent empty while the restriction is switched off -- see
        // BogoOffer::ORDER_TYPES_ENABLED.
        if (BogoOffer::orderTypesEnabled()) {
            $payload['order_types'] = $this->resource->order_types ?? [];
        }

        return $payload;
    }

    private function enrollment(): ?array
    {
        $enrollment = $this->resource->own_enrollment;

        if (! $enrollment) {
            return null;
        }

        $data = [
            'id' => (int) $enrollment->id,
            'status' => $enrollment->status,
            'requested_by' => $enrollment->requested_by,
            'rejection_reason' => $enrollment->rejection_reason,
            // So the app can word it as "Denied by admin" or "You declined".
            'rejected_by' => $enrollment->rejected_by,
            'bundle_price' => (float) $enrollment->bundle_price,
            'joined_at' => $enrollment->joined_at?->format('Y-m-d H:i:s'),
        ];

        if (! $this->withItems) {
            return $data;
        }

        $data['buy_items'] = $enrollment->items->where('type', 'buy')
            ->map(fn ($line) => (new BogoEnrollmentItemResource($line, $this->addOnLines))->render())->values()->all();
        $data['get_items'] = $enrollment->items->where('type', 'get')
            ->map(fn ($line) => (new BogoEnrollmentItemResource($line, $this->addOnLines))->render())->values()->all();

        return $data;
    }
}
