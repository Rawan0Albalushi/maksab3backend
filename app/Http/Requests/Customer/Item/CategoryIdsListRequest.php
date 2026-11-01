<?php

namespace App\Http\Requests\Customer\Item;

use App\CentralLogics\Helpers;
use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class CategoryIdsListRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'category_ids' => 'required',
        ];
    }

    public function filters(): array
    {
        return array_merge($this->apiContext($this), [
            'category_ids' => $this->input('category_ids') ? Helpers::decodeJsonToArray($this->input('category_ids')) : '',
        ]);
    }
}
