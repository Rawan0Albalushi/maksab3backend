<?php

namespace App\Http\Resources\Customer\Promotion;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class CouponResource extends BaseResource
{
    private const TYPE_STORE_WISE = 'store_wise';

    public function toArray(Request $request): array
    {
        $store = $this->resource->relationLoaded('store') ? $this->resource->getRelation('store') : null;

        return array_merge(parent::toArray($request), [
            'title' => $this->resource->title,
            'code' => $this->resource->code,
            'start_date' => $this->resource->start_date,
            'expire_date' => $this->resource->expire_date,
            'min_purchase' => $this->resource->min_purchase,
            'max_discount' => $this->resource->max_discount,
            'discount' => $this->resource->discount,
            'discount_type' => $this->resource->discount_type,
            'coupon_type' => $this->resource->coupon_type,
            'data' => $this->data($store),
            'store_id' => $this->storeId($store),
            'store' => $store ? [
                'id' => (int) $store->id,
                'name' => $store->name,
                'verified_seller' => Helpers::get_verified_seller_status($store, $store->storeConfig),
            ] : null,
        ]);
    }

    private function data(mixed $store): mixed
    {
        if ($this->resource->coupon_type === self::TYPE_STORE_WISE && $store) {
            return $store->name;
        }

        return $this->resource->data;
    }

    private function storeId(mixed $store): mixed
    {
        if ($this->resource->coupon_type === self::TYPE_STORE_WISE && $store) {
            return (int) $store->id;
        }

        return $this->resource->store_id;
    }
}
