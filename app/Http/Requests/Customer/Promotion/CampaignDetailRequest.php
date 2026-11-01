<?php

namespace App\Http\Requests\Customer\Promotion;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class CampaignDetailRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'basic_campaign_id' => 'required',
        ];
    }

    public function campaignId(): mixed
    {
        return $this->input('basic_campaign_id');
    }

    public function filters(): array
    {
        return [
            'zone_ids' => $this->zoneIds($this),
            'module_id' => $this->currentModuleId(),
            'longitude' => $this->header('longitude') ?? 0,
            'latitude' => $this->header('latitude') ?? 0,
        ];
    }
}
