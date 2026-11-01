<?php

namespace App\Http\Requests\Customer\Auth;

use App\Http\Requests\BaseRequest;

class FirebaseResetRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'sessionInfo' => 'required',
            'phoneNumber' => 'required',
            'code' => 'required',
            'is_reset_token' => 'nullable|boolean',
        ];
    }
}
