<?php

namespace App\Http\Resources\Common\Marketing;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class AppDownloadResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $settings = $this->resource['settings'] ?? [];

        return array_merge(parent::toArray($request), [
            'download_user_app_section_status' => (int) ($settings['download_user_app_section_status'] ?? 0),
            'download_user_app_title' => $settings['download_user_app_title'] ?? null,
            'download_user_app_links' => [
                'playstore_url' => Helpers::get_business_settings('app_url_android', false),
                'apple_store_url' => Helpers::get_business_settings('app_url_ios', false),
            ],
        ]);
    }
}
