<?php

namespace App\Http\Resources\Customer\Promotion;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ModuleBannerVideoResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $banners = $this->resource['banners'];

        return [
            'banner_contents' => array_map(
                fn ($content) => ['value' => $content->value],
                $this->resource['contents']
            ),
            'banner_type' => $banners['banner_type']->value ?? null,
            'banner_video' => $banners['banner_video']->value ?? null,
            'banner_image_full_url' => $this->fullUrl($banners['banner_image'] ?? null, 'promotional_banner'),
            'banner_video_content_full_url' => $this->fullUrl($banners['banner_video_content'] ?? null, 'promotional_banner/video'),
        ];
    }

    private function fullUrl(mixed $banner, string $directory): mixed
    {
        if (! $banner) {
            return null;
        }

        return Helpers::get_full_url($directory, $banner->value, $banner->storage[0]->value ?? 'public');
    }
}
