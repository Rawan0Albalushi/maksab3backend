<?php

namespace App\Http\Resources\Customer\Auth;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class AuthSessionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'token' => $this->resource['token'] ?? null,
            'is_phone_verified' => (int) ($this->resource['is_phone_verified'] ?? 1),
            'is_email_verified' => (int) ($this->resource['is_email_verified'] ?? 1),
            'is_personal_info' => (int) ($this->resource['is_personal_info'] ?? 0),
            'is_exist_user' => $this->resource['is_exist_user'] ?? null,
            'login_type' => $this->resource['login_type'] ?? null,
            'email' => $this->resource['email'] ?? null,
        ]);
    }
}
