<?php

namespace App\Http\Resources\Common\Marketing;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ReactLandingPageResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'hero_section' => $this->heroSection(),
            'trust_section' => $this->trustSection(),
            'available_zone_section' => $this->availableZoneSection(),
            'promotional_banner_section' => $this->promotionalBannerSection(),
            'user_app_download_section' => $this->userAppDownloadSection(),
            'popular_client_section' => $this->popularClientSection(),
            'seller_app_download_section' => $this->sellerAppDownloadSection(),
            'deliveryman_app_download_section' => $this->deliverymanAppDownloadSection(),
            'rider_app_download_section' => $this->riderAppDownloadSection(),
            'banner_section' => $this->bannerSection(),
            'testimonial_section' => $this->testimonialSection(),
            'gallery_section' => $this->gallerySection(),
            'highlight_section' => $this->highlightSection(),
            'faq_section' => $this->faqSection(),
            'footer_section' => $this->footerSection(),
            'company_intro_section' => $this->companyIntroSection(),

            'module_home_page_data_title' => $this->text('module_home_page_data_title'),
            'module_home_page_data_sub_title' => $this->text('module_home_page_data_sub_title'),
            'module_home_page_data_image' => isset($this->settings()['module_home_page_data_image'])
                ? Helpers::get_full_url('react_landing', $this->settings()['module_home_page_data_image'] ?? '', $this->settings()['module_home_page_data_image_storage'] ?? 'public', 'upload_image_1')
                : '',

            'module_vendor_registration_data_title' => $this->text('module_vendor_registration_data_title'),
            'module_vendor_registration_data_sub_title' => $this->text('module_vendor_registration_data_sub_title'),
            'module_vendor_registration_data_button_title' => $this->text('module_vendor_registration_data_button_title'),
            'module_vendor_registration_data_image' => isset($this->settings()['module_vendor_registration_data_image'])
                ? Helpers::get_full_url('react_landing', $this->settings()['module_vendor_registration_data_image'] ?? '', $this->settings()['module_vendor_registration_data_image_storage'] ?? 'public', 'upload_image_1')
                : '',

            'meta_title' => $this->text('meta_title'),
            'meta_description' => $this->text('meta_description'),
            'meta_image' => $this->imageUrl('landing/meta_image', 'meta_image'),
        ]);
    }

    private function heroSection(): array
    {
        return [
            'header_title' => $this->text('header_title'),
            'header_sub_title' => $this->text('header_sub_title'),
            'header_tag_line' => $this->text('header_tag_line'),
            'pick_location_title' => $this->text('pick_location_title'),
        ];
    }

    private function trustSection(): array
    {
        $section = [
            'trust_section_status' => $this->flag('trust_section_status'),
            'cards' => [],
        ];

        for ($i = 1; $i <= 4; $i++) {
            $section['cards'][] = [
                'status' => $this->flag("trust_status_card_{$i}"),
                'title' => $this->text("trust_title_card_{$i}"),
                'sub_title' => $this->text("trust_sub_title_card_{$i}"),
                'image_full_url' => $this->imageUrl('trust_section', "trust_image_card_{$i}"),
            ];
        }

        return $section;
    }

    private function availableZoneSection(): array
    {
        return [
            'available_zone_status' => $this->flag('available_zone_status'),
            'available_zone_title' => $this->text('available_zone_title'),
            'available_zone_short_description' => $this->text('available_zone_short_description'),
            'available_zone_list' => $this->resource['zones'],
        ];
    }

    private function promotionalBannerSection(): array
    {
        return [
            'promotion_banner_section_status' => $this->flag('promotional_banner_section_status'),
            'promotion_banners_full_url' => $this->resource['promotional_banners'] ?? [],
        ];
    }

    private function userAppDownloadSection(): array
    {
        return [
            'download_user_app_section_status' => $this->flag('download_user_app_section_status'),
            'download_user_app_title' => $this->text('download_user_app_title'),
            'download_user_app_sub_title' => $this->text('download_user_app_sub_title'),
            'download_user_app_image_full_url' => $this->imageUrl('download_user_app_image', 'download_user_app_image'),
            'download_user_app_button_title' => $this->text('download_user_app_button_title'),
            'download_user_app_button_sub_title' => $this->text('download_user_app_button_sub_title'),
            'download_user_app_links' => $this->userAppLinks(),
            'download_business_app_links' => $this->json('download_business_app_links'),
        ];
    }

    private function popularClientSection(): array
    {
        $section = [
            'popular_client_section_status' => $this->flag('popular_client_section_status'),
            'popular_client_title' => $this->text('popular_client_title'),
            'popular_client_sub_title' => $this->text('popular_client_sub_title'),
        ];

        foreach ($this->resource['popular_client_images'] as $image) {
            $section['cards'][] = [
                'image_full_url' => Helpers::get_full_url(
                    'popular_client_section',
                    $image,
                    $this->settings()['popular_client_image_storage'] ?? 'public'
                ),
            ];
        }

        return $section;
    }

    private function sellerAppDownloadSection(): array
    {
        return [
            'download_seller_app_section_status' => $this->flag('download_seller_app_section_status'),
            'download_seller_app_title' => $this->text('download_seller_app_title'),
            'download_seller_app_sub_title' => $this->text('download_seller_app_sub_title'),
            'download_seller_app_content_button_title' => $this->text('download_seller_app_button_title'),
            'download_seller_app_image_full_url' => $this->imageUrl('download_seller_app_section', 'download_seller_app_image'),
            'download_seller_app_button_title' => $this->text('download_seller_app_main_button_title'),
            'download_seller_app_button_sub_title' => $this->text('download_seller_app_main_button_sub_title'),
            'download_seller_app_links' => $this->json('download_seller_app_links'),
            'download_business_app_links' => $this->json('download_business_app_links'),
        ];
    }

    private function deliverymanAppDownloadSection(): array
    {
        return [
            'download_deliveryman_app_section_status' => $this->flag('download_dm_app_section_status'),
            'download_dm_app_title' => $this->text('download_dm_app_title'),
            'download_dm_app_sub_title' => $this->text('download_dm_app_sub_title'),
            'download_dm_app_content_button_title' => $this->text('download_dm_app_button_title'),
            'download_dm_app_image_full_url' => $this->imageUrl('download_dm_app_section', 'download_dm_app_image'),
            'download_dm_app_button_title' => $this->text('download_dm_app_main_button_title'),
            'download_dm_app_button_sub_title' => $this->text('download_dm_app_main_button_sub_title'),
            'download_dm_app_links' => $this->json('download_dm_app_links'),
            'download_business_app_links' => $this->json('download_business_app_links'),
        ];
    }

    private function riderAppDownloadSection(): array
    {
        if (! addon_published_status('RideShare')) {
            return [
                'download_rider_app_section_status' => 0,
                'download_rider_app_title' => null,
                'download_rider_app_sub_title' => null,
                'download_rider_app_content_button_title' => null,
                'download_rider_app_image_full_url' => null,
                'download_rider_app_button_title' => null,
                'download_rider_app_button_sub_title' => null,
                'download_rider_app_links' => null,
                'download_business_app_links' => null,
            ];
        }

        return [
            'download_rider_app_section_status' => $this->flag('download_rider_app_section_status'),
            'download_rider_app_title' => $this->text('download_rider_app_title'),
            'download_rider_app_sub_title' => $this->text('download_rider_app_sub_title'),
            'download_rider_app_content_button_title' => $this->text('download_rider_app_button_title'),
            'download_rider_app_image_full_url' => $this->imageUrl('download_rider_app_section', 'download_rider_app_image'),
            'download_rider_app_button_title' => $this->text('download_rider_app_main_button_title'),
            'download_rider_app_button_sub_title' => $this->text('download_rider_app_main_button_sub_title'),
            'download_rider_app_links' => $this->json('download_rider_app_links'),
            'download_business_app_links' => $this->json('download_business_app_links'),
        ];
    }

    private function bannerSection(): array
    {
        return [
            'banner_section_status' => $this->flag('banner_section_status'),
            'banner_iamge_full_url' => $this->imageUrl('banner_section', 'banner'),
        ];
    }

    private function testimonialSection(): array
    {
        return [
            'testimonial_section_status' => $this->flag('testimonial_section_status'),
            'testimonial_title' => $this->text('testimonial_title'),
            'testimonial_sub_title' => $this->text('testimonial_sub_title'),
            'testimonial_button_title' => $this->text('testimonial_button_title'),
            'testimonial_list' => $this->resource['testimonials'] ?? null,
        ];
    }

    private function gallerySection(): array
    {
        $section = [
            'gallery_section_status' => $this->flag('gallery_section_status'),
            'gallery_content_title' => $this->text('gallery_content_title'),
            'gallery_content_sub_title' => $this->text('gallery_content_sub_title'),
        ];

        for ($i = 1; $i <= 4; $i++) {
            $section['cards'][] = [
                'status' => $this->flag("gallery_image_{$i}_status"),
                'image_full_url' => $this->imageUrl('gallery_section', "gallery_image_{$i}"),
            ];
        }

        return $section;
    }

    private function highlightSection(): array
    {
        return [
            'highlight_section_status' => $this->flag('highlight_section_status'),
            'highlight_title' => $this->text('highlight_title'),
            'highlight_sub_title' => $this->text('highlight_sub_title'),
            'highlight_button_title' => null,
            'highlight_image_full_url' => $this->imageUrl('highlight_section', 'highlight_image'),
        ];
    }

    private function faqSection(): array
    {
        return [
            'faq_section_status' => $this->flag('faq_section_status'),
            'faq_title' => $this->text('faq_title'),
            'faq_list' => $this->resource['faqs'] ?? null,
        ];
    }

    private function footerSection(): array
    {
        return [
            'fixed_newsletter_title' => $this->text('fixed_newsletter_title'),
            'fixed_newsletter_sub_title' => $this->text('fixed_newsletter_sub_title'),
            'fixed_footer_description' => $this->text('fixed_footer_description'),
            'fixed_promotional_banner_full_url' => $this->imageUrl('promotional_banner', 'fixed_promotional_banner'),
        ];
    }

    private function companyIntroSection(): array
    {
        return [
            'company_title' => $this->text('company_title'),
            'company_sub_title' => $this->text('company_sub_title'),
            'company_description' => $this->text('company_description'),
            'company_button_name' => $this->text('company_button_name'),
            'company_button_url' => $this->text('company_button_url'),
        ];
    }

    private function userAppLinks(): array
    {
        $links = $this->json('download_user_app_links') ?? [];

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

    private function json(string $key): mixed
    {
        return isset($this->settings()[$key]) ? json_decode($this->settings()[$key], true) : null;
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
