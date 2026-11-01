<?php

namespace App\Http\Requests\Vendor\Item;

use App\Http\Requests\BaseRequest;

class ReviewReplyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required',
            'reply' => 'required|max:255',
        ];
    }
}
