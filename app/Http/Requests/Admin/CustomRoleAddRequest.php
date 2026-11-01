<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property string name
 * @property string|null modules
 * @property bool status
 * @property Carbon|null created_at
 * @property Carbon|null updated_at
 */
class CustomRoleAddRequest extends FormRequest
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
            'name' => 'required|unique:admin_roles|max:191',
            'name.0' => 'required',
            'modules'=>'required|array|min:1'
        ];
    }

    public function messages(): array
    {
        return [
            'name.0.required'=>translate('Default data is required'),
            'name.required'=>translate('messages.Role name is required'),
            'modules.required'=>translate('messages.Please select at least one module')
        ];
    }
}
