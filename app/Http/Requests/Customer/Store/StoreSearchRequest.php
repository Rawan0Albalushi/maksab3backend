<?php

namespace App\Http\Requests\Customer\Store;

use App\Http\Requests\BaseRequest;

class StoreSearchRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['name' => 'required'];
    }
}
