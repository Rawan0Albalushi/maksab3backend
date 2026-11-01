<?php

namespace App\Http\Resources\Customer\Wallet;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class BonusResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'title' => $this->resource->title,
            'bonus_type' => $this->resource->bonus_type,
            'bonus_amount' => (float) $this->resource->bonus_amount,
            'minimum_add_amount' => (float) $this->resource->minimum_add_amount,
            'end_date' => $this->resource->end_date,
        ]);
    }
}
