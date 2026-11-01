<?php

namespace App\Http\Requests\Common\System;

use App\Http\Requests\BaseRequest;

class NewsletterSubscribeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'email' => $this->emailRule('required', 'newsletters,email'),
        ];
    }

    public function payload(): array
    {
        return [
            'email' => $this->input('email'),
        ];
    }
}
