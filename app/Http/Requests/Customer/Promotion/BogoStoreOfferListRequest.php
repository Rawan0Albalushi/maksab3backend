<?php

namespace App\Http\Requests\Customer\Promotion;

use App\Http\Requests\BaseRequest;

/**
 * The BOGO offers one store runs. `store_id` accepts an id or a slug, the way every other store
 * identifier on this API does.
 */
class BogoStoreOfferListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'store_id' => 'required',
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
            'order_type' => 'nullable|string|in:delivery,home_delivery,take_away,dine_in',
        ];
    }

    public function filters(): array
    {
        return [
            'store_id' => $this->input('store_id'),
            'order_type' => $this->input('order_type'),
        ];
    }
}
