<?php

namespace App\Http\Requests\Vendor\Profile;

use App\Http\Requests\BaseRequest;

class FcmTokenRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'fcm_token' => 'required',
        ];
    }
}
