<?php

namespace App\Http\Requests\Customer\Promotion;

use App\Http\Requests\BaseRequest;

/**
 * Shared by the home card, the offer list and the offer detail screen.
 *
 * `order_type` narrows to offers the caller could actually place with. It is validated but only
 * applied while BogoOffer::ORDER_TYPES_ENABLED is on -- the rule is kept and simply relaxed, so
 * turning the constant back on restores it without a client change.
 */
class BogoOfferListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
            'order_type' => 'nullable|string|in:delivery,home_delivery,take_away,dine_in',
        ];
    }

    public function filters(): array
    {
        return [
            'order_type' => $this->input('order_type'),
        ];
    }
}
