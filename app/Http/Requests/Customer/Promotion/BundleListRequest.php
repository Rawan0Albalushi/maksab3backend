<?php

namespace App\Http\Requests\Customer\Promotion;

use App\Http\Requests\BaseRequest;

class BundleListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
            'store_id' => 'nullable|integer',
            'search' => 'nullable|string|max:100',
        ];
    }
}
