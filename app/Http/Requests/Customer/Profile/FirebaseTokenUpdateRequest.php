<?php

namespace App\Http\Requests\Customer\Profile;

use App\Http\Requests\BaseRequest;

class FirebaseTokenUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'cm_firebase_token' => 'required',
        ];
    }
}
