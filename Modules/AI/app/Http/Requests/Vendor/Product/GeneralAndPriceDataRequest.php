<?php

namespace Modules\AI\app\Http\Requests\Vendor\Product;

class GeneralAndPriceDataRequest extends AutoFillRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'description' => 'required|string',
            'module_type' => 'required',
        ];
    }

    public function payload(): array
    {
        return [
            'name' => $this->input('name'),
            'description' => $this->input('description'),
            'store_id' => $this->tokenStoreId(),
            'module_type' => $this->input('module_type'),
            'module_id' => $this->input('module_id'),
        ];
    }
}
