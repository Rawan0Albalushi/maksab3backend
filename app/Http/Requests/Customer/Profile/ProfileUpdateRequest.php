<?php

namespace App\Http\Requests\Customer\Profile;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        $userId = $this->user()?->id;
        $hostScope = fn ($query) => $query->where('tenant_id', 0)->where('sub_tenant_id', 0);

        return [
            'name' => 'required',
            'email' => $this->emailRule('required', Rule::unique('users', 'email')->ignore($userId)->where($hostScope)),
            'phone' => $this->phoneRule('required', Rule::unique('users', 'phone')->ignore($userId)->where($hostScope)),
            'image' => $this->imageRule(),
            'password' => $this->basicPasswordRule('nullable'),
            'session_info' => Rule::requiredIf(fn () => $this->input('verification_on') === 'phone'
                && $this->input('otp')
                && $this->input('verification_medium') === 'firebase'),
        ];
    }
    public function payload(): array
    {
        return [
            'name' => $this->input('name'),
            'email' => $this->input('email'),
            'phone' => $this->input('phone'),
            'password' => $this->input('password'),
            'image' => $this->file('image'),
            'otp' => $this->input('otp'),
            'button_type' => $this->input('button_type'),
            'verification_on' => $this->input('verification_on'),
            'verification_medium' => $this->input('verification_medium'),
            'session_info' => $this->input('session_info'),
        ];
    }
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        if ($user && $user->is_phone_verified == 1) {
            $this->merge(['phone' => $user->phone]);
        }
    }
}
