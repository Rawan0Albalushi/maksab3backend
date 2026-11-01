<?php

namespace App\Http\Resources\Common\Item;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class CommonConditionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'name' => $this->resource->name,
        ]);
    }
}
