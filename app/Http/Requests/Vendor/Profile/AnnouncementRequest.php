<?php

namespace App\Http\Requests\Vendor\Profile;

use App\Http\Requests\BaseRequest;

class AnnouncementRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'announcement_status' => 'required',
            'announcement_message' => 'required|max:255',
        ];
    }
}
