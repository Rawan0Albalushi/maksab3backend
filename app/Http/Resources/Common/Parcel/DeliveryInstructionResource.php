<?php

namespace App\Http\Resources\Common\Parcel;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class DeliveryInstructionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'instruction' => $this->resource->instruction,
        ]);
    }
}
