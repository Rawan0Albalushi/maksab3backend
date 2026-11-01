<?php

namespace App\Http\Requests\Customer\Search;

use App\Http\Requests\BaseRequest;

class CombinedSearchRequest extends BaseRequest
{
    public function rules(): array
    {
        return array_merge(['list_type' => 'required|in:item,store'], match ($this->query('data_type')) {
            'searched' => ['name' => 'required'],
            'brand' => ['brand_ids' => 'required'],
            'category' => ['category_ids' => 'required'],
            default => [],
        });
    }
}
