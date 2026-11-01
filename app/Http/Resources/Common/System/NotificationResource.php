<?php

namespace App\Http\Resources\Common\System;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class NotificationResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();

        if (! array_key_exists('image_full_url', $data) && method_exists($this->resource, 'getImageFullUrlAttribute')) {
            $data['image_full_url'] = $this->resource->image_full_url;
        }

        return $data;
    }
}
