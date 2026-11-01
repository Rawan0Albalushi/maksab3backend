<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;

/**
 * Own menu for the combination builder. The store is taken from the token, never from the
 * request, so a vendor cannot enumerate another store's items.
 */
class BogoOfferItemListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
        ];
    }
}
