<?php

namespace App\Http\Requests\Vendor\Item;

class AddonStatusRequest extends AddonIdRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => 'required|boolean',
        ]);
    }
}
