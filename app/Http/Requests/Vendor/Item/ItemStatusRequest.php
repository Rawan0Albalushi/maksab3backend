<?php

namespace App\Http\Requests\Vendor\Item;

class ItemStatusRequest extends ItemIdRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => 'required|boolean',
        ]);
    }
}
