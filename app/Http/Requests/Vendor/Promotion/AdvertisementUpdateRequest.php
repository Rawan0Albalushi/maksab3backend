<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\AdvertisementPayloadTrait;
use Illuminate\Contracts\Validation\Validator;

class AdvertisementUpdateRequest extends BaseRequest
{
    use AdvertisementPayloadTrait;

    public function rules(): array
    {
        return [
            'id' => 'required',
            'title' => 'nullable|max:255',
            'description' => 'nullable|max:65000',
            'dates' => ['required', self::DATE_RANGE_REGEX],
            'advertisement_type' => 'required|in:video_promotion,store_promotion',
            'cover_image' => $this->imageRule(),
            'profile_image' => $this->imageRule(),
            'video_attachment' => $this->videoRule(),
        ];
    }

    public function messages(): array
    {
        return $this->attachmentMessages();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $this->validateDateOrder($validator);
            $this->validateTranslationRows($validator);
        });
    }
}
