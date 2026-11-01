<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ModuleBannerResource extends BaseResource
{
    private const PARCEL_MODULE = 'parcel';

    public function toArray(Request $request): array
    {
        $banners = $this->resource['banners'];

        if (($this->resource['module_type'] ?? null) === self::PARCEL_MODULE) {
            return [
                'data' => collect($banners->items())
                    ->map(fn ($banner) => ['value_full_url' => $banner->value_full_url])
                    ->values()
                    ->all(),
                'pagination' => $this->resource['pagination'],
            ];
        }

        $payload = [];
        foreach ($banners->items() as $banner) {
            $payload[$banner->key . '_full_url'] = $banner->value_full_url;
        }

        return $payload;
    }
}
