<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class BannerDeleteRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'id' => 'required',
        ];
    }

    public function bannerId(): mixed
    {
        return $this->input('id');
    }

    public function filters(): array
    {
        return [
            'store_id' => $this->vendorStoreId($this),
        ];
    }
}
