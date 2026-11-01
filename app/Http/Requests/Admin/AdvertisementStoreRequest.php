<?php

namespace App\Http\Requests\Admin;

use App\Rules\VideoFile;
use App\Rules\ImageFile;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use App\CentralLogics\Helpers;
class AdvertisementStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules()
    {
        return [
            'store_id' => 'required',
            'title.*' => 'max:255',
            'description.*' => 'nullable|max:65000',
            'dates' => 'required',
            'advertisement_type' => 'required|in:video_promotion,store_promotion',
            'cover_image' => ImageFile::rules('required_if:advertisement_type,store_promotion'),
            'profile_image' => ImageFile::rules('required_if:advertisement_type,store_promotion'),
            'video_attachment' => VideoFile::rules('required_if:advertisement_type,video_promotion'),
            'title.0' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'store_id.required' => translate('messages.Please select a store'),
            'video_attachment.required_if' => translate('Your video attachment is missing'),
            'cover_image.required_if' => translate('Your cover image is missing'),
            'profile_image.required_if' => translate('Your profile image is missing'),
            'title.0.required'=>translate('Default title is required'),
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $dateRange = $this->dates;
            list($startDate, $endDate) = explode(' - ', $dateRange);
            $startDate = Carbon::createFromFormat('m/d/Y', trim($startDate))->startOfDay();
            $endDate = Carbon::createFromFormat('m/d/Y', trim($endDate))->endOfDay();

            if ($startDate < Carbon::today()) {
                $validator->errors()->add('date', translate('messages.Start date must be greater than or equal to today'));
            }

            if ($endDate < $startDate) {
                $validator->errors()->add('date', translate('messages.End date must be greater than start date'));
            }
        });
    }
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json(['errors' => Helpers::error_processor($validator)]);
        throw new ValidationException($validator, $response);
    }
}
