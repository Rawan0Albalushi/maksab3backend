<?php

namespace Modules\TaxModule\Http\Requests\Common\Tax;

use App\Http\Requests\BaseRequest;

class TaxListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'limit' => 'nullable|numeric',
            'offset' => 'nullable|numeric',
        ];
    }
}
