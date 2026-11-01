<?php

namespace App\Http\Requests\Vendor\Store;

use App\CentralLogics\Helpers;
use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class StoreBasicInfoUpdateRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'contact_number' => $this->phoneRule(),
            'logo' => $this->imageRule(),
            'cover_photo' => $this->imageRule(),
            'meta_image' => $this->imageRule(),
            'meta_title' => 'max:100',
            'translations' => 'required|array|min:2',
        ];
    }

    public function messages(): array
    {
        return [
            'translations.required' => translate('messages.Name and address in english is required'),
            'translations.min' => translate('messages.Name and address in english is required'),
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
            'address' => $translations[1]['value'] ?? null,
            'phone' => $this->input('contact_number'),
            'meta_title' => $this->input('meta_title'),
            'meta_description' => $this->input('meta_description'),
            'meta_data' => Helpers::formatMetaData($this->all(), $this->vendorStore($this)?->meta_data),
            'translations' => $translations,
            'logo_file' => $this->hasFile('logo') ? $this->file('logo') : null,
            'cover_photo_file' => $this->hasFile('cover_photo') ? $this->file('cover_photo') : null,
            'meta_image_file' => $this->hasFile('meta_image') ? $this->file('meta_image') : null,
        ];
    }
}
