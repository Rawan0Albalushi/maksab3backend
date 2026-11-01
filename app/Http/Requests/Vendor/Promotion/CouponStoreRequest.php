<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class CouponStoreRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'code' => 'required|unique:coupons|max:100',
            'start_date' => 'required',
            'expire_date' => 'required',
            'coupon_type' => 'required|in:free_delivery,default',
            'discount' => 'required_if:coupon_type,default',
            'max_discount' => 'exclude_unless:discount_type,percent|required|numeric|min:0.01',
            'translations' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'max_discount.required' => translate('Max discount is required for percentage discount type'),
            'max_discount.min' => translate('Max discount must be greater than zero for percentage discount type'),
            'translations.required' => translate('messages.Title in English is required'),
        ];
    }

    public function payload(): array
    {
        return [
            'code' => $this->input('code'),
            'limit' => $this->input('limit'),
            'coupon_type' => $this->input('coupon_type'),
            'start_date' => $this->input('start_date'),
            'expire_date' => $this->input('expire_date'),
            'min_purchase' => $this->input('min_purchase') ?? 0,
            'max_discount' => $this->input('max_discount') ?? 0,
            'discount' => $this->input('discount'),
            'discount_type' => $this->input('discount_type'),
            'customer_ids' => $this->input('customer_ids') ?? ['all'],
            'translations' => $this->translationRows($this),
        ];
    }
}
