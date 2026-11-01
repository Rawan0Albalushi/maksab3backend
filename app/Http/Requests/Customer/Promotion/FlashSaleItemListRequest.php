<?php

namespace App\Http\Requests\Customer\Promotion;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class FlashSaleItemListRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'flash_sale_id' => 'required',
        ];
    }

    public function filters(): array
    {
        return [
            'zone_ids' => $this->zoneIds($this),
            'module_id' => $this->currentModuleId(),
        ];
    }
}
