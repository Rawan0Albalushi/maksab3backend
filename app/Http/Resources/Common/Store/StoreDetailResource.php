<?php

namespace App\Http\Resources\Common\Store;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use App\Services\Store\StoreService;
use Illuminate\Http\Request;

class StoreDetailResource extends BaseResource
{
    private const IMAGE_URLS = [
        'logo_full_url', 'cover_photo_full_url', 'meta_image_full_url', 'tin_certificate_image_full_url',
    ];

    private const DROPPED = [
        'rating', 'items_count', 'campaigns_count', 'campaigns', 'pivot',
        'items_min_price', 'items_max_price', 'store_reviews_count', 'services_count',
    ];

    public function __construct(mixed $resource, private readonly array $extras = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $store = $this->resource;
        $ratings = app(StoreService::class)->calculateRating($store['rating']);
        $config = $store->storeConfig;
        $moduleType = $store->module?->module_type;
        $extraPackaging = Helpers::get_business_settings('extra_packaging_data');
        $packagingEnabled = ! empty($extraPackaging) && data_get($extraPackaging, $moduleType) == '1';
        $serviceLocations = $config?->choose_service_location ?: ['customer'];

        $data = $store->attributesToArray();
        $appends = [];

        foreach ($store->getAppends() as $append) {
            $appends[$append] = $data[$append] ?? null;
            unset($data[$append]);
        }

        foreach (self::IMAGE_URLS as $key) {
            $appends[$key] = $store->{$key};
        }

        $data['ratings'] = $store->rating ?? [];
        unset($data['rating']);

        $data['avg_rating'] = (float) $ratings['rating'];
        $data['rating_count'] = $moduleType === 'service'
            ? (int) $ratings['total']
            : (int) $this->reviewCount($store);
        $data['positive_rating'] = (float) $ratings['positive_rating'];
        $data['total_items'] = (int) ($moduleType === 'service' && service_addon_active()
            ? ($store['services_count'] ?? 0)
            : ($store['items_count'] ?? 0));
        $data['total_campaigns'] = $store['campaigns_count'];
        $data['min'] = (float) ($store['items_min_price'] ?? 0);
        $data['max'] = (float) ($store['items_max_price'] ?? 0);
        $data['is_recommended'] = $config && $config->is_recommended_deleted == 0 ? $config->is_recommended : false;
        $data['halal_tag_status'] = (bool) $config?->halal_tag_status;
        $data['verified_seller'] = (bool) Helpers::get_verified_seller_status($store, $config);
        $data['extra_packaging_status'] = (bool) ($packagingEnabled ? $config?->extra_packaging_status : false);
        $data['extra_packaging_amount'] = (float) ($packagingEnabled && $config?->extra_packaging_status == '1' ? $config?->extra_packaging_amount : 0);
        $data['self_delivery_system'] = (int) $store->sub_self_delivery;
        $data['current_opening_time'] = Helpers::getNextOpeningTime($store['schedules']) ?? 'closed';
        $data['show_low_stock_count'] = (bool) $config?->show_low_stock_count;
        $data['minimum_stock_for_warning'] = (int) $config?->minimum_stock_for_warning;
        $data['can_edit_order'] = (bool) (Helpers::get_business_settings('can_vendor_edit_order') == 1 ? $config?->can_edit_order : 0);
        $data['can_edit_booking'] = (bool) (service_setting_enabled('service_provider_can_edit_booking') ? $config?->can_edit_booking : 0);
        $data['instant_booking'] = (bool) (service_setting_enabled('service_instant_booking') ? $config?->instant_booking : 0);
        $data['repeat_booking'] = (bool) (service_setting_enabled('service_repeat_booking') ? $config?->repeat_booking : 0);
        $data['schedule_booking'] = (bool) (service_setting_enabled('service_schedule_booking') ? $config?->schedule_booking : 0);
        $data['manage_service_setup'] = (bool) ($config?->manage_service_setup ?? true);
        $data['show_reviews_provider_panel'] = (bool) ($config?->show_reviews_provider_panel ?? true);
        $data['choose_service_location'] = $serviceLocations;
        $data['service_location_customer_status'] = in_array('customer', $serviceLocations);
        $data['service_location_provider_status'] = service_setting_enabled('service_at_provider_place') && in_array('provider', $serviceLocations);
        $data['serviceman_can_cancel_booking'] = (bool) (service_setting_enabled('service_serviceman_cancel_booking_req') ? $config?->serviceman_can_cancel_booking : 0);

        // The three promotion strips, under the same keys and in the same shape the store
        // listings use. Attached upstream by StorePayloadTrait::attachStorePromotions(), which
        // resolves them for the whole page at once; defaulted here so a caller that builds this
        // resource without going through the trait still emits the keys rather than omitting
        // them for some stores and not others.
        //
        // bogo_offers is empty on a SERVICE provider by design -- a service cannot be bought one
        // and given one free. Bundles and coupons apply there as they do anywhere else.
        $data['bogo_offers'] = (array) ($store->getAttribute('bogo_offers') ?? []);
        $data['bundles'] = (array) ($store->getAttribute('bundles') ?? []);
        $data['coupons'] = (array) ($store->getAttribute('coupons') ?? []);

        foreach ($this->extras as $key => $value) {
            $data[$key] = $value;
        }

        foreach ($appends as $key => $value) {
            $data[$key] = $value;
        }

        foreach (self::DROPPED as $key) {
            unset($data[$key]);
        }

        $relations = $store->relationsToArray();
        unset($relations['campaigns']);

        return $data + $relations;
    }

    private function reviewCount(mixed $store): int
    {
        return (int) ($store['store_reviews_count'] ?? 0);
    }
}
