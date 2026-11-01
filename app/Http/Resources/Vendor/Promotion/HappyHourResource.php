<?php

namespace App\Http\Resources\Vendor\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * One happy hour as the store app needs it.
 *
 * `visibility_status` uses the same five words the BOGO endpoint answers with -- running,
 * scheduled, ended, not_visible, not_applicable -- so the app renders one badge for both
 * promotions rather than learning two vocabularies for the same idea.
 *
 * `can_resubmit` is present but always false: a happy hour carries no selection to rework,
 * so a denied store cancels and joins again.
 *
 * Every derived key is attached by HappyHourVendorService::primeRows(); nothing here queries.
 */
class HappyHourResource extends BaseResource
{
    public function __construct($resource, private readonly bool $withSchedule = false, private readonly array $dates = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $visibility = $this->resource->visibility ?? [];

        $payload = array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'slug' => $this->resource->slug,
            'title' => $this->resource->title,
            'short_description' => $this->resource->short_description,
            'cover_image_full_url' => $this->resource->cover_image_full_url,
            'icon_full_url' => $this->resource->icon_full_url,
            'module_id' => (int) $this->resource->module_id,
            'discount' => (float) $this->resource->discount,
            'min_order_amount' => $this->resource->min_order_amount !== null
                ? (float) $this->resource->min_order_amount
                : null,
            'is_permanent' => (bool) $this->resource->is_permanent,
            'duration_type' => $this->resource->duration_type,
            'weekly_days' => $this->resource->weekly_days ?? [],
            'start_date' => $this->resource->start_date?->format('Y-m-d'),
            'end_date' => $this->resource->end_date?->format('Y-m-d'),
            'start_time' => $this->resource->start_time,
            'end_time' => $this->resource->end_time,
            // Whether the window is open at this moment, which is a schedule question the columns
            // alone cannot answer.
            'is_running_now' => (bool) ($this->resource->is_running ?? false),
            'status' => (int) $this->resource->status,

            'is_expired' => (bool) ($this->resource->has_ended ?? false),
            'is_eligible' => (bool) ($this->resource->is_eligible ?? false),
            'ineligible_reason' => $this->resource->ineligible_reason,

            'enrollment_state' => $this->resource->state,
            'enrollment_state_label' => $this->resource->state_label,
            'rejection_note' => $this->resource->rejection_note,

            'visibility_status' => $visibility['status'] ?? null,
            'visibility_label' => $visibility['label'] ?? null,
            'visibility_reasons' => $visibility['reasons'] ?? [],

            'enrollment' => $this->enrollment(),
            'actions' => $this->resource->actions ?? [],
        ]);

        if ($this->withSchedule) {
            $payload['dates'] = $this->dates;
        }

        return $payload;
    }

    private function enrollment(): ?array
    {
        $enrollment = $this->resource->own_enrollment;

        if (! $enrollment) {
            return null;
        }

        return [
            'id' => (int) $enrollment->id,
            'status' => $enrollment->status,
            'requested_by' => $enrollment->requested_by,
            'rejection_reason' => $enrollment->rejection_reason,
            'rejected_by' => $enrollment->rejected_by,
            'joined_at' => $enrollment->joined_at?->format('Y-m-d H:i:s'),
        ];
    }
}
