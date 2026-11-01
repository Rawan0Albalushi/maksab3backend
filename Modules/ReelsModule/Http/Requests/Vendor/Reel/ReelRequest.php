<?php

namespace Modules\ReelsModule\Http\Requests\Vendor\Reel;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

abstract class ReelRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function filters(): array
    {
        return ['store_id' => $this->vendorStoreId($this)];
    }
}
