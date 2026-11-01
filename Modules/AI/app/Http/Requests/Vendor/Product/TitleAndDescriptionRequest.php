<?php

namespace Modules\AI\app\Http\Requests\Vendor\Product;

class TitleAndDescriptionRequest extends AutoFillRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'langCode' => 'required',
            'module_type' => 'required',
        ];
    }

    public function payload(): array
    {
        return [
            'name' => $this->input('name'),
            'lang_code' => $this->input('langCode'),
            'module_type' => $this->input('module_type'),
        ];
    }
}
