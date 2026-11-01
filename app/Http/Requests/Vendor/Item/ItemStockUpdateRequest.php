<?php

namespace App\Http\Requests\Vendor\Item;

use App\Http\Requests\BaseRequest;

class ItemStockUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'product_id' => 'required',
            'current_stock' => 'required',
        ];
    }

    public function payload(): array
    {
        return $this->input();
    }
}
