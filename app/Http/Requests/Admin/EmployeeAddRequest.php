<?php

namespace App\Http\Requests\Admin;

use App\Rules\ImageFile;
use App\Rules\PhoneNumber;
use App\Rules\EmailAddress;
use App\Rules\StrongPassword;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property string|null f_name
 * @property string|null l_name
 * @property string|null phone
 * @property string email
 * @property string|null image
 * @property string|null password
 * @property string|null remember_token
 * @property Carbon|null created_at
 * @property Carbon|null updated_at
 * @property int|null role_id
 * @property int|null zone_id
 * @property bool is_logged_in
 */
class EmployeeAddRequest extends FormRequest
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
            'f_name' => 'required',
            'l_name' => 'nullable|max:100',
            'role_id' => 'required|not_in:1',
            'image' => ImageFile::rules('required'),
            'email' => EmailAddress::rules('required', 'admins'),
            'phone' => PhoneNumber::rules('required', 'admins'),
            'password' => StrongPassword::rules('required'),
        ];
    }

    public function messages(): array
    {
        return [
            'f_name.required' => translate('messages.First name is required'),
            'role_id.not_in' => translate('Unauthorized'),
            'password.required' => translate('The password is required'),
        ];
    }
}
