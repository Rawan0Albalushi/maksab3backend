<?php

namespace App\Http\Requests\Common\System;

use App\Http\Requests\BaseRequest;

class PlaceSearchRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['search_text' => 'required'];
    }

    public function searchText(): string
    {
        return (string) $this->input('search_text');
    }
}
