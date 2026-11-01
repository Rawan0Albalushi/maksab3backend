<?php

namespace App\Http\Requests\Vendor\Item;

class StoreCategoryUpdateRequest extends StoreCategoryStoreRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'image' => $this->imageRule(),
        ]);
    }
}
