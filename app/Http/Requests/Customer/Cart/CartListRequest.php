<?php

namespace App\Http\Requests\Customer\Cart;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class CartListRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'guest_id' => $this->user ? 'nullable' : 'required',
            'store_id' => 'required',
        ];
    }

    public function filters(): array
    {
        return array_merge($this->cartOwner($this), [
            'store_id' => $this->cartStoreId($this),
        ]);
    }
}
