<?php

namespace App\Http\Resources\Common\Chat;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class AutomatedMessageResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'message' => $this->resource->message,
        ]);
    }
}
