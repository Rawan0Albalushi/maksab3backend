<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;

/**
 * The store app's offer list.
 *
 * `type` is the tab. Offers whose order types the store cannot serve stay visible but are not
 * joinable, which is why there is no filter for them -- `is_eligible` on the row says so instead.
 */
class BogoOfferListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:all,not_joined,pending,admin_requested,approved,rejected',
        ];
    }

    public function filters(): array
    {
        return [
            'search' => $this->input('search'),
            'type' => $this->input('type', 'all'),
        ];
    }
}
