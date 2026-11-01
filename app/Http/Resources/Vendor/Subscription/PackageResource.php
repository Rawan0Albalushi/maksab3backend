<?php

namespace App\Http\Resources\Vendor\Subscription;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class PackageResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), array_merge(
            $this->resource->attributesToArray(),
            [
                'translations' => $this->resource->relationLoaded('translations')
                    ? $this->resource->translations
                    : [],
            ]
        ));
    }
}
