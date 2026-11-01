<?php

namespace App\Http\Requests\Vendor\Report;

use App\Http\Requests\BaseRequest;

class DateRangeRequest extends BaseRequest
{
    protected const DATE_FORMAT = 'Y-m-d';

    public function rules(): array
    {
        return [
            'from' => 'required|date_format:'.static::DATE_FORMAT,
            'to' => 'required|date_format:'.static::DATE_FORMAT.'|after_or_equal:from',
        ];
    }

    public function filters(): array
    {
        return [
            'from' => $this->input('from'),
            'to' => $this->input('to'),
            'search' => $this->input('search'),
        ];
    }
}
