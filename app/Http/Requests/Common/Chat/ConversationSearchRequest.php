<?php

namespace App\Http\Requests\Common\Chat;

use App\Http\Requests\BaseRequest;

class ConversationSearchRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required',
        ];
    }

    public function filters(): array
    {
        return [
            'name' => $this->input('name'),
            'type' => $this->input('type'),
        ];
    }
}
