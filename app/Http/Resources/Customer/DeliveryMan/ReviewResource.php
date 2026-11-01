<?php

namespace App\Http\Resources\Customer\DeliveryMan;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ReviewResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge($this->resource->toArray(), [
            'attachment' => is_array($this->resource->attachment)
                ? $this->resource->attachment
                : json_decode((string) $this->resource->attachment),
            'attachment_full_url' => $this->resource->attachment_full_url,
        ]);
    }
}
