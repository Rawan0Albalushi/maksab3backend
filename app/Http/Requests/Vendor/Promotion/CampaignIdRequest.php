<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;

class CampaignIdRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'campaign_id' => 'required',
        ];
    }
}
