<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * One happy hour as a customer sees it.
 *
 * The same object wherever a window is named -- the home banner, a store card, the stores list --
 * so a client parses it once. `is_running_now` is a schedule question the columns cannot answer,
 * which is why it is stated rather than left to be derived from start_time and end_time.
 *
 * There is deliberately no zone here. In this codebase a happy hour is scoped by MODULE, and its
 * geographic reach follows from the stores that enrolled in it.
 */
class HappyHourResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
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
            'duration_type' => $this->resource->duration_type,
            'is_permanent' => (bool) $this->resource->is_permanent,
            'weekly_days' => $this->resource->weekly_days ?? [],
            'start_time' => $this->resource->start_time,
            'end_time' => $this->resource->end_time,
            'start_date' => $this->resource->start_date?->format('Y-m-d'),
            'end_date' => $this->resource->end_date?->format('Y-m-d'),
            'is_running_now' => $this->resource->isRunningNow(),
        ]);
    }
}
