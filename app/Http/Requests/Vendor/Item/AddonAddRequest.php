<?php

namespace App\Http\Requests\Vendor\Item;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class AddonAddRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'name' => 'required',
            'addon_category_id' => 'required',
            'price' => 'required|numeric',
            'translations' => 'required|array|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'translations.required' => translate('messages.Name and description in english is required'),
            'translations.min' => translate('messages.Name and description in english is required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['translations' => $this->translationRows($this)]);
    }

    public function payload(): array
    {
        $translations = $this->translationRows($this);

        return [
            'name' => $translations[0]['value'] ?? null,
            'price' => $this->input('price'),
            'addon_category_id' => $this->input('addon_category_id'),
            'store_id' => $this->vendorStoreId($this),
            'tax_ids' => $this->taxIds(),
            'translations' => $translations,
        ];
    }

    private function taxIds(): array
    {
        $decoded = json_decode($this->input('tax_ids') ?? '[]', true);

        return is_array($decoded) ? $decoded : [];
    }
}
