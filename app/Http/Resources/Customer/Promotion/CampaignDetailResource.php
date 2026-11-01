<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\Customer\Store\StoreResource;
use Illuminate\Http\Request;

class CampaignDetailResource extends CampaignResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'stores' => StoreResource::collection($this->whenLoaded('stores')),
        ]);
    }
}
