<?php

namespace App\Http\Resources\Common\System;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class PageMetaResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'title' => $this->resource['title'] ?? null,
            'description' => $this->resource['description'] ?? null,
            'image_full_url' => $this->resource['image_full_url'] ?? null,
            'meta_data' => $this->resource['meta_data'] ?? [],
        ]);
    }
}
