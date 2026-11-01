<?php

namespace App\Http\Resources\Common\System;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ReasonResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'reason' => $this->resource->reason,
        ]);
    }
}
