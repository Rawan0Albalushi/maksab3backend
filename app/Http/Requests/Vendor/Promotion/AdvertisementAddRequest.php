<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\AdvertisementPayloadTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Carbon;

class AdvertisementAddRequest extends BaseRequest
{
    use AdvertisementPayloadTrait;

    public function rules(): array
    {
        return [
            'title' => 'nullable|max:255',
            'description' => 'nullable|max:65000',
            'dates' => ['required', self::DATE_RANGE_REGEX],
            'advertisement_type' => 'required|in:video_promotion,store_promotion',
            'cover_image' => $this->imageRule('required_if:advertisement_type,store_promotion'),
            'profile_image' => $this->imageRule('required_if:advertisement_type,store_promotion'),
            'video_attachment' => $this->videoRule('required_if:advertisement_type,video_promotion'),
        ];
    }

    public function messages(): array
    {
        return array_merge($this->attachmentMessages(), [
            'video_attachment.required_if' => translate('Your video attachment is missing'),
            'cover_image.required_if' => translate('Your cover image is missing'),
            'profile_image.required_if' => translate('Your profile image is missing'),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            [$startDate] = $this->dateRange();

            if ($startDate && $startDate < Carbon::today()) {
                $validator->errors()->add('date', translate('messages.Start date must be greater than or equal to today'));
            }

            $this->validateDateOrder($validator);
            $this->validateTranslationRows($validator);
        });
    }
}
