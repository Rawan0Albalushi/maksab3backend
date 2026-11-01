<?php

namespace App\Http\Requests\DeliveryMan\Report;

class LoyaltyPointListRequest extends EarningReportRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['type' => 'nullable|in:credit,debit,both']);
    }

    public function filters(): array
    {
        return array_merge(parent::filters(), ['type' => $this->input('type', 'both')]);
    }
}
