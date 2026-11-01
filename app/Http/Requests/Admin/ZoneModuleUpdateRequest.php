<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @property array name
 * @property array coordinates
 * @property string store_wise_topic
 * @property string customer_wise_topic
 * @property string deliveryman_wise_topic
 * @property string|int cash_on_delivery
 * @property string|int digital_payment
 * @property string|int offline_payment
 * @property array module_data
 * @property array lang
 */
class ZoneModuleUpdateRequest extends FormRequest
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
            'module_data' => 'required'
        ];
    }

    public function messages(): array
    {
        return [
            'module_data.required' => translate('messages.Business module data is required'),
        ];
    }
}
