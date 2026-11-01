<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class BannerResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => data_get($this->resource, 'id'),
            'title' => data_get($this->resource, 'title'),
            'type' => data_get($this->resource, 'type'),
            'image_full_url' => data_get($this->resource, 'image_full_url'),
            'link' => data_get($this->resource, 'link'),
            'store' => data_get($this->resource, 'store'),
            'item' => data_get($this->resource, 'item'),
            'provider' => $this->when(
                array_key_exists('provider', (array) $this->resource),
                fn () => data_get($this->resource, 'provider')
            ),
            'category' => $this->when(
                array_key_exists('category', (array) $this->resource),
                fn () => data_get($this->resource, 'category')
            ),
            'service' => $this->when(
                array_key_exists('service', (array) $this->resource),
                fn () => data_get($this->resource, 'service')
            ),
        ]);
    }
}
