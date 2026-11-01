<?php

namespace App\Http\Requests\Vendor\Item;

class StoreCategoryPriorityRequest extends StoreCategoryIdRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'priority' => 'required|integer|in:0,1,2',
        ]);
    }
}
