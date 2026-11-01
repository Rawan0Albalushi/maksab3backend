<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;

/**
 * Join, or rework a selection. Both write the same frozen snapshot, so both validate the same way.
 *
 * The arrays are only shape-checked here. Whether the quantities add up to the offer's buy_qty and
 * get_qty, whether every item belongs to this store, and whether the combination is already in use
 * are all decided by HandlesBogoEnrollment -- one rule shared with the panel, rather than a second
 * copy here that can drift.
 *
 * Both fields accept a JSON string as well as an array, because the app posts them encoded.
 */
class BogoEnrollmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'buy_items' => 'required',
            'get_items' => 'required',
        ];
    }

    public function payload(): array
    {
        return [
            'buy_items' => $this->input('buy_items'),
            'get_items' => $this->input('get_items'),
        ];
    }
}
