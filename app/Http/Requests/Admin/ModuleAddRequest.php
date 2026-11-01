<?php

namespace App\Http\Requests\Admin;

use App\Rules\ImageFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * @property array lang
 * @property int id
 * @property array module_name
 * @property string module_type
 * @property string|null thumbnail
 * @property bool status
 * @property int stores_count
 * @property Carbon|null created_at
 * @property Carbon|null updated_at
 * @property string|null icon
 * @property int theme_id
 * @property array description
 * @property bool all_zone_service
 */
class ModuleAddRequest extends FormRequest
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
            'module_name' => 'required|unique:modules|max:100',
            'module_type'=>'required|not_in:rental,ride-share,service',
            'icon' => ImageFile::rules('required'),
            'thumbnail' => ImageFile::rules('required'),
            'module_name.0' => 'required',
            'description.0' => 'required',
            'short_description.*' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'module_name.required' => translate('messages.Name is required'),
            'module_name.0.required'=>translate('Default name is required'),
            'module_type.not_in'=>translate('messages.This module type cannot be created manually'),
            'description.0.required'=>translate('Default description is required'),
        ];
    }
}
