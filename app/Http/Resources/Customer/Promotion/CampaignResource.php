<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class CampaignResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'image_full_url' => $this->resource->image_full_url,
            'available_date_starts' => $this->resource->start_date?->format('Y-m-d'),
            'available_date_ends' => $this->resource->end_date?->format('Y-m-d'),
            'start_time' => $this->resource->start_time,
            'end_time' => $this->resource->end_time,
        ]);
    }
}
