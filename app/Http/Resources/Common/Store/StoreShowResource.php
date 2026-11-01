<?php

namespace App\Http\Resources\Common\Store;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use App\Models\ProCustomerBenefitSetting;
use App\Services\Payment\ProCustomerBenefitSettingService;
use App\Services\Payment\ProCustomerSubscriptionService;
use App\Services\Store\StoreService;
use App\Services\System\DataSettingService;
use Illuminate\Http\Request;

class StoreShowResource extends BaseResource
{
    public function __construct(mixed $resource, private readonly array $extras = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $store = $this->resource;
        $service = app(StoreService::class);
        $ratings = $service->calculateRating($store->rating);
        $topItems = $service->topItemRows([$store->id])[$store->id] ?? [];

        $row = (new StoreListResource($store))
            ->withOptions(['with_items' => true, 'top_items' => $topItems])
            ->toArray($request);

        return array_merge($row, [
            'avg_rating' => $ratings['rating'],
            'rating_count' => (int) $ratings['total'],
            'positive_rating' => $ratings['positive_rating'],
            'ratings' => $store->rating,
            'reviews_comments_count' => (int) ($store->reviews_comments_count ?? 0),
            'total_items' => (int) ($store->items_count ?? 0),
            'ad' => (int) $store->ad,
            'phone' => $store->phone,
            'email' => $store->email,
            'address' => $store->address,
            'latitude' => $store->latitude,
            'longitude' => $store->longitude,
            'zone_id' => (int) $store->zone_id,
            'vendor_id' => (int) $store->vendor_id,
            'minimum_order' => (float) $store->minimum_order,
            'tax' => (float) $store->tax,
            'delivery' => (int) $store->delivery,
            'take_away' => (int) $store->take_away,
            'self_delivery_system' => (int) $store->sub_self_delivery,
            'minimum_shipping_charge' => (float) $store->minimum_shipping_charge,
            'maximum_shipping_charge' => $store->maximum_shipping_charge === null ? null : (float) $store->maximum_shipping_charge,
            'per_km_shipping_charge' => (float) $store->per_km_shipping_charge,
            'off_day' => $store->off_day,
            'schedule_order' => (int) $store->schedule_order,
            'order_place_to_schedule_interval' => (int) $store->order_place_to_schedule_interval,
            'schedules' => $this->schedules(),
            'prescription_order' => (int) $store->prescription_order,
            'cutlery' => (int) $store->cutlery,
            'veg' => (int) $store->veg,
            'non_veg' => (int) $store->non_veg,
            'announcement' => (int) $store->announcement,
            'announcement_message' => $store->announcement_message,
            'pos_system' => (int) $store->pos_system,
            'featured' => (int) $store->featured,
            'reviews_section' => (int) $store->reviews_section,
            'order_count' => (int) $store->order_count,
            'store_business_model' => $store->store_business_model,
            'store_sub' => $this->subscription(),
            'active_coupons' => $this->coupons(),
            'store_discount' => $this->discount(),
            'meta_title' => $store->meta_title,
            'meta_description' => $store->meta_description,
            'meta_image_full_url' => $store->meta_image_full_url,
            'is_pro_discount_available' => $this->proDiscountAvailable(),
        ], $this->packaging(), $this->extras);
    }

    private function proDiscountAvailable(): int
    {
        $moduleType = $this->resource->module?->module_type;

        if (! $moduleType || ! in_array($moduleType, ProCustomerBenefitSetting::DISCOUNT_MODULE_TYPES, true)) {
            return 0;
        }

        if (! app(ProCustomerSubscriptionService::class)->memberFeatureEnabled()) {
            return 0;
        }

        $flags = app(DataSettingService::class)->proBenefitStatusFlags();

        if ((int) ($flags['discount_status'] ?? 0) !== 1) {
            return 0;
        }

        $lookupKey = ($flags['discount_setup_mode'] ?? 'central') === 'individual' ? $moduleType : null;
        $config = app(ProCustomerBenefitSettingService::class)->getSettings('discount', $lookupKey);

        return (float) ($config['percentage'] ?? 0) > 0 ? 1 : 0;
    }

    private function packaging(): array
    {
        $config = $this->resource->storeConfig;
        $settings = Helpers::get_business_settings('extra_packaging_data');
        $enabled = ! empty($settings) && data_get($settings, $this->resource->module?->module_type) == '1';

        return [
            'extra_packaging_status' => (int) ($enabled ? $config?->extra_packaging_status : 0),
            'extra_packaging_amount' => (float) ($enabled && $config?->extra_packaging_status == '1' ? $config?->extra_packaging_amount : 0),
        ];
    }

    private function schedules(): array
    {
        if (! $this->resource->relationLoaded('schedules')) {
            return [];
        }

        return $this->resource->schedules->map(fn ($schedule) => [
            'id' => (int) $schedule->id,
            'store_id' => (int) $schedule->store_id,
            'day' => (int) $schedule->day,
            'opening_time' => $schedule->opening_time,
            'closing_time' => $schedule->closing_time,
        ])->values()->all();
    }

    private function coupons(): array
    {
        if (! $this->resource->relationLoaded('activeCoupons')) {
            return [];
        }

        return $this->resource->activeCoupons->map(fn ($coupon) => [
            'id' => (int) $coupon->id,
            'title' => $coupon->title,
            'code' => $coupon->code,
            'coupon_type' => $coupon->coupon_type,
            'start_date' => $coupon->start_date,
            'expire_date' => $coupon->expire_date,
            'min_purchase' => (float) $coupon->min_purchase,
            'max_discount' => (float) $coupon->max_discount,
            'discount' => (float) $coupon->discount,
            'discount_type' => $coupon->discount_type,
            'limit' => $coupon->limit,
            'status' => (int) $coupon->status,
            'total_uses' => (int) $coupon->total_uses,
            'store_id' => $coupon->store_id === null ? null : (int) $coupon->store_id,
            'module_id' => $coupon->module_id === null ? null : (int) $coupon->module_id,
            'slug' => $coupon->slug,
        ])->values()->all();
    }

    private function subscription(): ?array
    {
        $subscription = $this->resource->relationLoaded('store_sub') ? $this->resource->store_sub : null;

        if (! $subscription) {
            return null;
        }

        return [
            'id' => (int) $subscription->id,
            'package_id' => (int) $subscription->package_id,
            'store_id' => (int) $subscription->store_id,
            'expiry_date' => $subscription->expiry_date,
            'max_order' => $subscription->max_order,
            'max_product' => $subscription->max_product,
            'pos' => (int) $subscription->pos,
            'mobile_app' => (int) $subscription->mobile_app,
            'chat' => (int) $subscription->chat,
            'review' => (int) $subscription->review,
            'self_delivery' => (int) $subscription->self_delivery,
            'status' => (int) $subscription->status,
            'is_trial' => (int) $subscription->is_trial,
        ];
    }

    private function discount(): ?array
    {
        $discount = $this->resource->relationLoaded('discount') ? $this->resource->discount : null;

        if (! $discount) {
            return null;
        }

        return [
            'id' => (int) $discount->id,
            'store_id' => (int) $discount->store_id,
            'discount' => (float) $discount->discount,
            'discount_type' => $discount->discount_type ?? 'percent',
            'min_purchase' => (float) $discount->min_purchase,
            'max_discount' => (float) $discount->max_discount,
            'start_date' => $discount->start_date,
            'end_date' => $discount->end_date,
            'start_time' => $discount->start_time,
            'end_time' => $discount->end_time,
        ];
    }
}
