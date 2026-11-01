<?php

namespace App\Http\Requests\Vendor\DeliveryMan;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class DeliveryManSearchRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'search' => 'required',
        ];
    }

    public function filters(): array
    {
        return [
            'search' => $this->input('search'),
            'store_id' => $this->vendorStoreId($this),
        ];
    }
}
