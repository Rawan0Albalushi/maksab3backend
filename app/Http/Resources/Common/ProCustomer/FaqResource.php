<?php

namespace App\Http\Resources\Common\ProCustomer;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class FaqResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'question' => $this->resource->question,
            'answer' => $this->resource->answer,
        ]);
    }
}
