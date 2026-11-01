<?php

namespace App\Http\Resources\Common\Marketing;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class FlutterLandingPageResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $awsBaseUrl = rtrim(config('filesystems.disks.s3.url') ?? '', '/') . '/' . ltrim(config('filesystems.disks.s3.bucket') . '/');

        return array_merge(parent::toArray($request), [
            'base_urls' => [
                'fixed_header_image' => asset('storage/app/public/fixed_header_image'),
                'special_criteria_image' => asset('storage/app/public/special_criteria'),
                'download_user_app_image' => asset('storage/app/public/download_user_app_image'),
            ],

            's3_base_urls' => [
                'fixed_header_image' => $awsBaseUrl . 'fixed_header_image',
                'special_criteria_image' => $awsBaseUrl . 'special_criteria',
                'download_user_app_image' => $awsBaseUrl . 'download_user_app_image',
            ],

            'fixed_header_title' => $this->text('fixed_header_title'),
            'fixed_header_sub_title' => $this->text('fixed_header_sub_title'),
            'fixed_header_image' => $this->text('fixed_header_image'),
            'fixed_header_image_full_url' => $this->imageUrl('fixed_header_image', 'fixed_header_image'),
            'fixed_module_title' => $this->text('fixed_module_title'),
            'fixed_module_sub_title' => $this->text('fixed_module_sub_title'),
            'fixed_location_title' => $this->text('fixed_location_title'),
            'join_seller_title' => $this->text('join_seller_title'),
            'join_seller_sub_title' => $this->text('join_seller_sub_title'),
            'join_seller_button_name' => $this->text('join_seller_button_name'),
            'join_seller_status' => $this->flag('join_seller_flutter_status'),
            'join_delivery_man_title' => $this->text('join_delivery_man_title'),
            'join_delivery_man_sub_title' => $this->text('join_delivery_man_sub_title'),
            'join_delivery_man_button_name' => $this->text('join_delivery_man_button_name'),
            'join_delivery_man_status' => $this->flag('join_DM_flutter_status'),

            'download_user_app_title' => $this->text('download_user_app_title'),
            'download_user_app_sub_title' => $this->text('download_user_app_sub_title'),
            'download_user_app_image' => $this->text('download_user_app_image'),
            'download_user_app_image_full_url' => $this->imageUrl('download_user_app_image', 'download_user_app_image'),

            'special_criterias' => $this->resource['special_criterias'] ?? null,

            'download_user_app_links' => $this->userAppLinks(),
            'available_zone_status' => $this->flag('available_zone_status'),
            'available_zone_title' => $this->text('available_zone_title'),
            'available_zone_short_description' => $this->text('available_zone_short_description'),
            'available_zone_image' => $this->text('available_zone_image'),
            'available_zone_image_full_url' => $this->imageUrl('available_zone_image', 'available_zone_image'),
            'available_zone_list' => $this->resource['zones'],
        ]);
    }

    private function userAppLinks(): array
    {
        $links = isset($this->settings()['download_user_app_links'])
            ? (json_decode($this->settings()['download_user_app_links'], true) ?? [])
            : [];

        $links['playstore_url_status'] = (int) ($links['playstore_url_status'] ?? 0);
        $links['apple_store_url_status'] = (int) ($links['apple_store_url_status'] ?? 0);
        $links['playstore_url'] = Helpers::get_business_settings('app_url_android', false);
        $links['apple_store_url'] = Helpers::get_business_settings('app_url_ios', false);

        return $links;
    }

    private function settings(): array
    {
        return $this->resource['settings'] ?? [];
    }

    private function text(string $key): mixed
    {
        return $this->settings()[$key] ?? null;
    }

    private function flag(string $key): int
    {
        return (int) ($this->settings()[$key] ?? 0);
    }

    private function imageUrl(string $directory, string $key): mixed
    {
        return Helpers::get_full_url(
            $directory,
            $this->settings()[$key] ?? null,
            $this->settings()[$key . '_storage'] ?? 'public'
        );
    }
}
