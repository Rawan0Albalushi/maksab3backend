<?php

namespace Modules\ReelsModule\Http\Resources\Vendor\Reel;

use Illuminate\Http\Request;
use Modules\ReelsModule\Http\Resources\BaseReelResource;

class ReelListResource extends BaseReelResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'store_id' => $this->resource->store_id,
            'module_id' => $this->resource->module_id,
            'module_type' => $this->resource->module_type,
            'description' => $this->resource->description,
            'thumbnail_full_url' => $this->resource->thumbnail_full_url,
            'video_full_url' => $this->resource->video_full_url,
            'is_always_visible' => $this->resource->is_always_visible,
            'start_date' => $this->resource->start_date,
            'end_date' => $this->resource->end_date,
            'status' => $this->resource->status,
            'reel_status_label' => $this->resource->reel_status_label,
            'total_views' => (int) $this->resource->total_views,
            'total_likes' => (int) $this->resource->total_likes,
            'total_store_visits' => (int) $this->resource->total_store_visits,
            'order_count' => $this->resource->order_count,
            'total_sale_amount' => $this->resource->total_sale_amount,
            'created_at' => $this->resource->created_at,
            'productable_type' => $this->resource->productable_type,
            'productable_id' => $this->resource->productable_id,
            'order_now_button' => $this->resource->order_now_button,
            'store' => [
                'id' => $this->resource->store?->id,
                'name' => $this->resource->store?->name,
                'address' => $this->resource->store?->address,
                'phone' => $this->resource->store?->phone,
                'logo_full_url' => $this->resource->store?->logo_full_url,
            ],
            'productable' => $this->resource->productable ? [
                'id' => $this->resource->productable->id,
                'name' => $this->resource->productable->name,
            ] : null,
        ]);
    }
}
