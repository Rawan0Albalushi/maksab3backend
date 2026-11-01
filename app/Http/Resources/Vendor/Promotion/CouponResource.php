<?php

namespace App\Http\Resources\Vendor\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class CouponResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'code' => $this->resource->code,
            'start_date' => $this->resource->start_date,
            'expire_date' => $this->resource->expire_date,
            'min_purchase' => $this->resource->min_purchase,
            'max_discount' => $this->resource->max_discount,
            'discount' => $this->resource->discount,
            'discount_type' => $this->resource->discount_type,
            'coupon_type' => $this->resource->coupon_type,
            'limit' => $this->resource->limit,
            'status' => $this->resource->status,
            'total_uses' => $this->resource->total_uses,
            'created_at' => $this->resource->created_at,
            'data' => $this->decoded($this->resource->getRawOriginal('data')),
            'customer_id' => $this->decoded($this->resource->getRawOriginal('customer_id')),
            'translations' => $this->when(
                $this->resource->relationLoaded('translations'),
                fn () => $this->resource->getRelation('translations')
            ),
        ]);
    }

    private function decoded(mixed $value): mixed
    {
        return is_array($value) ? $value : json_decode((string) $value, true);
    }
}
