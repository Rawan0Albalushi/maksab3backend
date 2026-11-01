<?php

namespace App\Http\Resources\Customer\Profile;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class AddressResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'address_type' => $this->resource->address_type,
            'address' => $this->resource->address,
            'contact_person_name' => $this->resource->contact_person_name,
            'contact_person_number' => $this->resource->contact_person_number,
            'latitude' => $this->resource->latitude,
            'longitude' => $this->resource->longitude,
            'road' => $this->resource->road,
            'house' => $this->resource->house,
            'floor' => $this->resource->floor,
            'zone_id' => $this->resource->zone_id,
            'zone_ids' => $this->resource->zone_ids ?? [],
        ]);
    }
}
