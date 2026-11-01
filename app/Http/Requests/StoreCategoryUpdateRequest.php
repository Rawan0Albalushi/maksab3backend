<?php

namespace App\Http\Requests;

use App\Rules\ImageFile;
use App\CentralLogics\Helpers;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreCategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'image' => ImageFile::rules('nullable'),
            'priority' => 'nullable|integer|in:0,1,2',
        ];

        if (auth('admin')->check()) {
            $rules['store_id'] = 'required|exists:stores,id';
        }

        if ($this->filled('translations')) {
            $rules['translations'] = 'required';
        } else {
            $rules['name'] = 'required|array';
            $rules['name.0'] = 'required|max:255';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'store_id.required' => translate('messages.Store is required'),
            'store_id.exists' => translate('messages.Store is invalid'),
            'name.0.required' => translate('messages.Default name is required'),
            'translations.required' => translate('messages.Default name is required'),
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        if ($this->expectsJson() || $this->is('api/*')) {
            throw new ValidationException(
                $validator,
                response()->json(['errors' => Helpers::error_processor($validator)], 403)
            );
        }
        parent::failedValidation($validator);
    }
}
