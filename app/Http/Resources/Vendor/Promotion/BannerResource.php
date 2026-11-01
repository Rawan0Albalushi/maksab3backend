<?php

namespace App\Http\Resources\Vendor\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class BannerResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'status' => $this->status,
            'featured' => $this->featured,
            'image_full_url' => $this->image_full_url,
            'default_link' => $this->default_link,
            'created_at' => $this->created_at,
            'translations' => $this->whenLoaded('translations'),
        ]);
    }
}
