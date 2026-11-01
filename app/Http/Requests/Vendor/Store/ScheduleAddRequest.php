<?php

namespace App\Http\Requests\Vendor\Store;

use App\Http\Requests\BaseRequest;

class ScheduleAddRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'opening_time' => 'required|date_format:H:i:s',
            'closing_time' => 'required|date_format:H:i:s|after:opening_time',
        ];
    }

    public function messages(): array
    {
        return [
            'closing_time.after' => translate('messages.End time must be after the start time'),
        ];
    }

    public function payload(): array
    {
        return [
            'day' => $this->input('day'),
            'opening_time' => $this->input('opening_time'),
            'closing_time' => $this->input('closing_time'),
        ];
    }
}
