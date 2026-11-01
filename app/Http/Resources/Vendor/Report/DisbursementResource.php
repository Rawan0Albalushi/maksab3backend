<?php

namespace App\Http\Resources\Vendor\Report;

use App\Http\Resources\BaseResource;
use App\Models\Store;
use Illuminate\Http\Request;

class DisbursementResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), array_merge(
            $this->resource->attributesToArray(),
            [
                'store' => $this->resource->relationLoaded('store') && $this->resource->store
                    ? $this->resource->store->append(Store::IMAGE_URL_APPENDS)
                    : null,
                'withdraw_method' => $this->withdrawMethod(),
            ]
        ));
    }

    private function withdrawMethod(): mixed
    {
        if (! $this->resource->relationLoaded('withdraw_method')) {
            return null;
        }

        $method = $this->resource->withdraw_method;

        if ($method && is_string($method->method_fields)) {
            $method->method_fields = json_decode($method->method_fields, true);
        }

        return $method;
    }
}
