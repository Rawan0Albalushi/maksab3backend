<?php

namespace App\Http\Requests\Vendor\Item;

class ItemOrganicRequest extends ItemIdRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'organic' => 'required|boolean',
        ]);
    }
}
