<?php

namespace App\Http\Requests\Admin;

use App\Rules\PhoneNumber;
use App\Rules\EmailAddress;
use App\Rules\StrongPassword;
use App\Models\DeliveryMan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property array|string title
 * @property array translations
 * @property string|null|array description
 * @property string bonus_type
 * @property float bonus_amount
 * @property float minimum_add_amount
 * @property float maximum_bonus_amount
 * @property Carbon|null start_date
 * @property Carbon|null end_date
 * @property bool status
 * @property Carbon|null created_at
 * @property Carbon|null updated_at
 * @property array lang
 */
class DeliveryManAddRequest extends FormRequest
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
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'identity_number' => 'required|max:30',
            'email' => EmailAddress::rules('required', 'delivery_men'),
            'phone' => PhoneNumber::rules('required', 'delivery_men'),
            'zone_id' => 'required',
            'earning' => 'required',
            'vehicle_id' => 'required',
            'password' => StrongPassword::rules('required'),
            'referral_code' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $referer = DeliveryMan::where('ref_code', $value)->first();
                        if (!$referer || !$referer->status) {
                            $fail(translate('Referer code not found'));
                        }
                    }
                }
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'f_name.required' => translate('messages.First name is required'),
            'zone_id.required' => translate('messages.Select a zone'),
            'vehicle_id.required' => translate('messages.Select a vehicle'),
            'earning.required' => translate('Select deliveryman type'),
            'password.required' => translate('The password is required'),
        ];
    }
}
