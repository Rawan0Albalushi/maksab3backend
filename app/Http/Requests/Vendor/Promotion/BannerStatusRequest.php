<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class BannerStatusRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'id' => 'required',
            'status' => 'required|boolean',
        ];
    }

    public function bannerId(): mixed
    {
        return $this->input('id');
    }

    public function status(): string
    {
        return (string) (int) $this->boolean('status');
    }

    public function payload(): array
    {
        return [
            'store_id' => $this->vendorStoreId($this),
        ];
    }
}
