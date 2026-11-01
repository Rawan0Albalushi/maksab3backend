<?php

namespace App\Http\Requests\Customer\Item;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ItemListFiltersTrait;

class ItemCategoryIdsRequest extends BaseRequest
{
    use ItemListFiltersTrait;

    public function rules(): array
    {
        return ['category_ids' => 'required'];
    }
}
