<?php

namespace App\Http\Resources\Common\Chat;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ChatUserResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'f_name' => $this->resource->f_name,
            'l_name' => $this->resource->l_name,
            'phone' => $this->resource->phone,
            'image_full_url' => $this->resource->image_full_url,
        ]);
    }
}
