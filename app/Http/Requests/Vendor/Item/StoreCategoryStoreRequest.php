<?php

namespace App\Http\Requests\Vendor\Item;

use App\Http\Requests\BaseRequest;

class StoreCategoryStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return array_merge([
            'image' => $this->imageRule('required'),
            'priority' => 'nullable|integer|in:0,1,2',
        ], $this->nameRules());
    }

    public function messages(): array
    {
        return [
            'name.0.required' => translate('messages.Default name is required'),
            'translations.required' => translate('messages.Default name is required'),
        ];
    }

    public function translationRows(): array
    {
        $raw = $this->input('translations');

        if (is_array($raw)) {
            return $raw;
        }

        return is_string($raw) ? (json_decode($raw, true) ?? []) : [];
    }

    public function defaultName(): ?string
    {
        $rows = $this->translationRows();
        $default = collect($rows)->firstWhere('locale', 'default');

        return $default['value'] ?? ($rows[0]['value'] ?? null);
    }

    protected function nameRules(): array
    {
        if ($this->filled('translations')) {
            return ['translations' => 'required'];
        }

        return ['name' => 'required|array', 'name.0' => 'required|max:255'];
    }
}
