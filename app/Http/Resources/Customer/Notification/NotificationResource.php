<?php

namespace App\Http\Resources\Customer\Notification;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class NotificationResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'image_full_url' => $this->resource->image_full_url ?? null,
            'data' => $this->resource->data,
        ]);
    }
}
