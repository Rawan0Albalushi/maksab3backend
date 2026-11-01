<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Contracts\Validation\Validator;

class BannerUpdateRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'id' => 'required',
            'image' => $this->imageRule(),
        ];
    }

    public function messages(): array
    {
        return [
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if (count($this->translationRows($this)) < 1) {
                $validator->errors()->add('translations', translate('messages.Title and description in english is required'));
            }
        });
    }

    public function bannerId(): mixed
    {
        return $this->input('id');
    }

    public function payload(): array
    {
        $rows = $this->translationRows($this);

        return [
            'title' => $rows[0]['value'] ?? null,
            'translations' => $rows,
            'image' => $this->file('image'),
            'default_link' => $this->input('default_link'),
            'store_id' => $this->vendorStoreId($this),
        ];
    }
}
