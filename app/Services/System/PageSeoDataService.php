<?php

namespace App\Services\System;

use App\CentralLogics\Helpers;
use App\Models\PageSeoData;
use App\Services\BaseService;

class PageSeoDataService extends BaseService
{
    public function findForPage(?string $pageName): ?array
    {
        if (! $pageName || ! in_array($pageName, Helpers::seoPageList())) {
            return null;
        }

        $page = PageSeoData::where('status', 1)->where('page_name', $pageName)
            ->select(['id', 'title', 'description', 'image', 'meta_data'])
            ->first();

        return $page ? [
            'title' => $page->title,
            'description' => $page->description,
            'image_full_url' => $page->image_full_url,
            'meta_data' => $page->meta_data ?? [],
        ] : null;
    }
}
