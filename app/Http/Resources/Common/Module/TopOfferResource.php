<?php

namespace App\Http\Resources\Common\Module;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TopOfferResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'discount' => (float) ($this->resource->top_offer_value ?? 0),
            'discount_type' => $this->resource->top_offer_type ?? null,
        ]);
    }
}
