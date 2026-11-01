<?php

namespace App\Http\Requests\Customer\Profile;

use App\Http\Requests\BaseRequest;

class AddressDeleteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'address_id' => 'required',
        ];
    }
}
