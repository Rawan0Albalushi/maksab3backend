<?php

namespace App\Http\Requests\Vendor\Item;

use App\CentralLogics\Helpers;
use App\Http\Requests\BaseRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

abstract class ItemFormRequest extends BaseRequest
{
    public function rules(): array
    {
        $storeId = (int) ($this->vendorStore()?->id ?? 0);

        return [
            'category_id' => 'required',
            'store_category_id' => [
                Helpers::hasAnyStoreCategory($storeId) ? 'required' : 'nullable',
                Rule::exists('store_categories', 'id')->where(fn ($query) => $query->where('store_id', $storeId)),
            ],
            'price' => 'required|numeric|between:'.Helpers::getDecimalPlaces().',999999999999.999',
            'discount' => 'nullable|numeric|min:0',
            'video_upload_type' => 'nullable|in:file,link',
            'video' => $this->videoRule('nullable', Helpers::productVideoMaxUploadSizeMb() * 1024),
            'video_link' => ['nullable', $this->videoLinkRule()],
            'remove_video' => 'nullable|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => translate('messages.Category required'),
            'store_category_id.required' => translate('messages.Store category required'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $discount = $this->input('discount_type') === 'percent'
                ? ((float) $this->input('price') / 100) * (float) $this->input('discount')
                : (float) $this->input('discount');

            if ($discount > 0 && (float) $this->input('price') <= $discount) {
                $validator->errors()->add('unit_price', translate('messages.Discount can not be more than or equal'));
            }

            if (count($this->translationRows()) < 2) {
                $validator->errors()->add('translations', translate('messages.Name and description in english is required'));
            }
        });
    }

    public function vendorStore(): mixed
    {
        return $this->input('vendor')?->stores[0] ?? null;
    }

    public function translationRows(): array
    {
        $raw = $this->input('translations');

        return is_array($raw) ? $raw : (json_decode((string) $raw, true) ?? []);
    }

    public function payload(): array
    {
        $files = array_filter([
            'image' => $this->file('image'),
            'video' => $this->file('video'),
            'meta_image' => $this->file('meta_image'),
            'item_images' => $this->file('item_images'),
        ], fn ($file) => $file !== null);

        return array_merge($this->input(), $files, [
            'translations' => $this->translationRows(),
            'inputs' => $this->input(),
        ]);
    }

    private function videoLinkRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if (! $value) {
                return;
            }

            if (! filter_var($value, FILTER_VALIDATE_URL)
                || ! in_array(strtolower(parse_url($value, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
                $fail('Please enter a valid video link.');
            }
        };
    }
}
