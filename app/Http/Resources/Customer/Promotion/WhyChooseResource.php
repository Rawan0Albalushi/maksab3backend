<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class WhyChooseResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'title' => $this->resource->title,
            'short_description' => $this->resource->short_description,
            'image_full_url' => $this->resource->image_full_url,
        ]);
    }
}
