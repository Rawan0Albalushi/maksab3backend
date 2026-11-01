<?php

namespace App\Http\Requests\Admin;

use App\Rules\ImageFile;
use App\CentralLogics\Helpers;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * @property int id
 * @property array title
 * @property string type
 * @property string|null image
 * @property bool status
 * @property string data
 * @property Carbon|null created_at
 * @property Carbon|null updated_at
 * @property int zone_id
 * @property int module_id
 * @property bool featured
 * @property string|null default_link
 * @property string created_by
 * @property array lang
 */
class BannerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|max:191',
            'image' => ImageFile::rules('nullable'),
            'banner_type' => 'required',
            'zone_id' => 'required',
            'store_id' => 'required_if:banner_type,store_wise',
            'item_id' => 'required_if:banner_type,item_wise',
            'title.0' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'zone_id.required' => translate('messages.Select a zone'),
            'store_id.required_if'=> translate('messages.Store is required when banner type is store wise'),
            'item_id.required_if'=> translate('Item is required when banner type is item wise'),
            'title.0.required'=>translate('Default data is required'),
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        $response = response()->json(['errors' => Helpers::error_processor($validator)]);
        throw new ValidationException($validator, $response);
    }
}
