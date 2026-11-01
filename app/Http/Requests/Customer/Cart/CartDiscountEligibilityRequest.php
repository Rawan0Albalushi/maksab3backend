<?php

namespace App\Http\Requests\Customer\Cart;

use App\Http\Requests\BaseRequest;

/**
 * What a store-wide discount would come to on this cart.
 *
 * Per store, because the threshold is that store's: a basket split across three stores can
 * qualify at one and fall short at the other two.
 */
class CartDiscountEligibilityRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'guest_id' => $this->user ? 'nullable' : 'required',
            'store_id' => 'required',
        ];
    }
}
