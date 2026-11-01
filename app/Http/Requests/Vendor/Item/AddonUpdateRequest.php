<?php

namespace App\Http\Requests\Vendor\Item;

class AddonUpdateRequest extends AddonAddRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'id' => 'required',
            'price' => 'required',
        ]);
    }
}
