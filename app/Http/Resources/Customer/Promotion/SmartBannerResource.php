<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class SmartBannerResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'title' => $this->resource->title,
            'subtitle' => $this->resource->subtitle,
            'image_full_url' => $this->resource->image_full_url,
            'position' => $this->resource->position,
            'redirect_type' => $this->resource->redirect_type,
            'redirect_target_id' => $this->resource->redirect_target_id,
            'module_id' => $this->resource->module_id,
        ]);
    }
}
