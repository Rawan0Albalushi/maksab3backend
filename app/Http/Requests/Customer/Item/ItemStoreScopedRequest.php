<?php

namespace App\Http\Requests\Customer\Item;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ItemListFiltersTrait;

class ItemStoreScopedRequest extends BaseRequest
{
    use ItemListFiltersTrait;

    public function rules(): array
    {
        return ['store_id' => 'required'];
    }
}
