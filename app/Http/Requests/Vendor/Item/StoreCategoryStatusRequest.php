<?php

namespace App\Http\Requests\Vendor\Item;

class StoreCategoryStatusRequest extends StoreCategoryIdRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => 'required|in:0,1',
        ]);
    }
}
