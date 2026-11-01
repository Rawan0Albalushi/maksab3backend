<?php

namespace App\Http\Requests\Customer\Store;

use App\Http\Requests\BaseRequest;

class StoreReviewListRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['store_id' => 'required'];
    }
}
