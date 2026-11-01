<?php

namespace App\Http\Requests\Vendor\Store;

use App\CentralLogics\Helpers;
use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Contracts\Validation\Validator;

class StoreSetupUpdateRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'delivery' => 'required|boolean',
            'prescription_order' => 'required|boolean',
            'take_away' => 'required|boolean',
            'schedule_order' => 'required|boolean',
            'veg' => 'required|boolean',
            'non_veg' => 'required|boolean',
            'minimum_order' => 'required|numeric|min:0.01',
            'gst' => 'required_if:gst_status,1',
            'minimum_delivery_time' => 'required|numeric',
            'maximum_delivery_time' => 'required|numeric',
            'delivery_time_type' => 'required|in:min,hours,days',
            // Matches Vendor\BusinessSettingsController::store_setup()'s web-panel rule — this
            // API endpoint previously had no numeric/bounds check at all (TC_10).
            'maximum_delivery_charge' => 'nullable|numeric|min:0|max:999999999',
        ];
    }

    public function messages(): array
    {
        return [
            'gst.required_if' => translate('GST can not be empty'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $selfDelivery = (bool) $this->vendorStore($this)?->sub_self_delivery;

        // Numeric/bounds constraints only apply here (not unconditionally in rules()) because
        // these fields are only meaningful, and only sent, when self-delivery is on — matching
        // how the web-panel controller gates the same checks (TC_10).
        $validator->sometimes(
            'per_km_delivery_charge',
            ['required_with:minimum_delivery_charge', 'numeric', 'min:0', 'max:999999999'],
            fn () => $selfDelivery
        );
        $validator->sometimes(
            'minimum_delivery_charge',
            ['required_with:per_km_delivery_charge', 'numeric', 'min:0', 'max:99999999.99'],
            fn () => $selfDelivery
        );

        $validator->after(function (Validator $validator) {
            if (! $this->input('take_away') && ! $this->input('delivery')) {
                $validator->errors()->add('delivery_or_take_way', translate('messages.Can not disable both take away and delivery'));
            }

            if (Helpers::get_business_settings('toggle_veg_non_veg', false) == 1
                && ! $this->input('veg') && ! $this->input('non_veg')) {
                $validator->errors()->add('veg_non_veg', translate('messages.Veg non veg disable by admin'));
            }

            if (
                $this->filled('maximum_delivery_charge')
                && $this->filled('minimum_delivery_charge')
                && (float) $this->input('minimum_delivery_charge') > (float) $this->input('maximum_delivery_charge')
            ) {
                $validator->errors()->add(
                    'maximum_delivery_charge',
                    translate('messages.Maximum delivery charge must be greater than minimum delivery charge')
                );
            }
        });
    }

    public function payload(): array
    {
        $store = $this->vendorStore($this);
        $selfDelivery = (bool) $store?->sub_self_delivery;

        return [
            'store' => [
                'delivery' => $this->input('delivery'),
                'prescription_order' => $this->input('prescription_order'),
                'take_away' => $this->input('take_away'),
                'schedule_order' => $this->input('schedule_order'),
                'veg' => $this->input('veg') ?? 0,
                'non_veg' => $this->input('non_veg') ?? 0,
                'cutlery' => $this->input('cutlery') ?? 0,
                'free_delivery' => $this->input('free_delivery') ?? 0,
                'minimum_order' => $this->input('minimum_order'),
                'gst' => json_encode(['status' => $this->input('gst_status'), 'code' => $this->input('gst')]),
                'minimum_shipping_charge' => $selfDelivery ? ($this->input('minimum_delivery_charge') ?? 0) : $store?->minimum_shipping_charge,
                'per_km_shipping_charge' => $selfDelivery ? ($this->input('per_km_delivery_charge') ?? 0) : $store?->per_km_shipping_charge,
                'maximum_shipping_charge' => $store ? ($this->input('maximum_delivery_charge') ?? 0) : $store?->maximum_delivery_charge,
                'delivery_time' => $this->input('minimum_delivery_time').'-'.$this->input('maximum_delivery_time').' '.$this->input('delivery_time_type'),
                'order_place_to_schedule_interval' => $this->input('order_place_to_schedule_interval'),
            ],
            'config' => [
                'halal_tag_status' => $this->input('halal_tag_status') ?? 0,
                'extra_packaging_status' => $this->input('extra_packaging_status') ?? 0,
                'extra_packaging_amount' => $this->input('extra_packaging_amount'),
                'minimum_stock_for_warning' => $this->input('minimum_stock_for_warning'),
                'show_low_stock_count' => $this->input('show_low_stock_count'),
            ],
        ];
    }
}
