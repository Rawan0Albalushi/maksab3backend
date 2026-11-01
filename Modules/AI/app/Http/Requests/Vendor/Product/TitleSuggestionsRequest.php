<?php

namespace Modules\AI\app\Http\Requests\Vendor\Product;

class TitleSuggestionsRequest extends AutoFillRequest
{
    public function rules(): array
    {
        return [
            'keywords' => 'required|string|max:255',
        ];
    }

    public function usageContext(): array
    {
        return ['store_id' => $this->input('store_id'), 'request_type' => null];
    }

    public function keywords(): array
    {
        return array_map('trim', explode(',', $this->input('keywords')));
    }
}
