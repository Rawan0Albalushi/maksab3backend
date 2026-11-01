<?php

namespace App\Http\Requests\DeliveryMan\Report;

use App\Http\Requests\BaseRequest;

class EarningReportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'type' => 'nullable|in:all,delivery_fee,delivery_tips',
        ];
    }

    public function filters(): array
    {
        return [
            'date_range' => $this->input('date_range'),
            'start_date' => $this->input('start_date'),
            'end_date' => $this->input('end_date'),
            'type' => $this->input('type', 'all'),
        ];
    }
}
