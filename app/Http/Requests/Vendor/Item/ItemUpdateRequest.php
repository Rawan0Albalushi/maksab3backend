<?php

namespace App\Http\Requests\Vendor\Item;

class ItemUpdateRequest extends ItemFormRequest
{
    public function rules(): array
    {
        return parent::rules() + ['id' => 'required'];
    }
}
