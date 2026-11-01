<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property array unit
 * @property array lang
 */
class UnitAddRequest extends FormRequest
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
            'unit'=>'required|unique:units,unit',
            'unit.0' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'unit.0.required'=>translate('Default unit is required'),
        ];
    }
}
