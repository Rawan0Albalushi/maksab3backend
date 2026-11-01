<?php

namespace App\Services\System;

use App\Models\FAQ;
use App\Services\BaseService;
use App\Support\Cache\ApiCache;

class FaqService extends BaseService
{
    public function getGeneral(): mixed
    {
        return FAQ::whereNull('faqable_id')->get();
    }

    public function getCachedByUserType(string $userType, int $limit = 5): mixed
    {
        return ApiCache::remember('reference_list', ['faq', $userType, $limit, app()->getLocale()], function () use ($userType, $limit) {
            return FAQ::latest()
                ->whereNull('faqable_id')
                ->where('user_type', $userType)
                ->take($limit)
                ->get();
        });
    }
}
