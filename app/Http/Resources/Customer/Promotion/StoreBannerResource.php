<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class StoreBannerResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'image_full_url' => $this->image_full_url,
            'default_link' => $this->default_link,
        ]);
    }
}
