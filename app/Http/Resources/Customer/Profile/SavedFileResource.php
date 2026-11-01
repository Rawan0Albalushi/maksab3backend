<?php

namespace App\Http\Resources\Customer\Profile;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class SavedFileResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'file_name' => $this->resource->file_name,
            'image_full_url' => $this->resource->image_full_url,
        ]);
    }
}
