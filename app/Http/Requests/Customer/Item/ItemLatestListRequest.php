<?php

namespace App\Http\Requests\Customer\Item;

use App\Traits\Api\ItemListFiltersTrait;

class ItemLatestListRequest extends ItemStoreScopedRequest
{
    use ItemListFiltersTrait;

    public function rules(): array
    {
        return array_merge(parent::rules(), ['category_id' => 'required']);
    }
}
