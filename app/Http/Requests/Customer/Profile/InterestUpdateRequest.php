<?php

namespace App\Http\Requests\Customer\Profile;

use App\Http\Requests\BaseRequest;

class InterestUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'interest' => 'required|array',
        ];
    }
}
