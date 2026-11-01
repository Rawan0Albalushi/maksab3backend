<?php

namespace App\Http\Resources\Common\Parcel;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ParcelCategoryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'image_full_url' => $this->resource->image_full_url,
            // The category's one ADDITIONAL charge — added to whatever the delivery rule priced
            // the parcel at (owner decision 2026-09-03).
            'charge' => (float) $this->resource->charge,
            // Deprecated with that decision and reported as 0.00 rather than removed: shipped apps
            // read these keys and N9 forbids dropping them, but a category no longer prices
            // anything, so returning the stored rates would quote a fee nothing charges.
            'parcel_per_km_shipping_charge' => 0.0,
            'parcel_minimum_shipping_charge' => 0.0,
        ]);
    }
}
