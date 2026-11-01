<?php

namespace App\Services\System;

use App\Models\SocialMedia;
use App\Services\BaseService;
use App\Support\Cache\ApiCache;

class SocialMediaService extends BaseService
{
    public function getActive(): array
    {
        return SocialMedia::active()->get()->toArray();
    }

    public function getActiveCached(): mixed
    {
        return ApiCache::remember('reference_list', 'social_media_active', function () {
            return SocialMedia::active()->get();
        });
    }
}
