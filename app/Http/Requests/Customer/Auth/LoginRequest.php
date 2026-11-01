<?php

namespace App\Http\Requests\Customer\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return array_merge(
            ['login_type' => 'required|in:manual,otp,social'],
            match ($this->input('login_type')) {
                'manual' => [
                    'email_or_phone' => 'required',
                    'password' => 'required|min:6',
                    'field_type' => 'required|in:phone,email',
                ],
                'otp' => [
                    'phone' => $this->phoneRule(),
                    'otp' => Rule::requiredIf(fn () => (bool) $this->input('verified')),
                ],
                'social' => [
                    'token' => 'required',
                    'unique_id' => 'required',
                    'email' => $this->emailRule('required_if:medium,google,facebook'),
                    'medium' => 'required|in:google,facebook,apple',
                ],
                default => [],
            }
        );
    }

    public function payload(): array
    {
        return [
            'login_type' => $this->input('login_type'),
            'email_or_phone' => $this->input('email_or_phone'),
            'password' => $this->input('password'),
            'field_type' => $this->input('field_type'),
            'phone' => $this->input('phone'),
            'otp' => $this->input('otp'),
            'verified' => $this->input('verified'),
            'has_verified' => $this->has('verified'),
            'token' => $this->input('token'),
            'unique_id' => $this->input('unique_id'),
            'email' => $this->input('email'),
            'medium' => $this->input('medium'),
            'platform' => $this->input('platform'),
            'access_token' => $this->input('access_token'),
            'guest_id' => $this->input('guest_id'),
        ];
    }
}
