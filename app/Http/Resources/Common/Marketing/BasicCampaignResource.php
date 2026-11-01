<?php

namespace App\Http\Resources\Common\Marketing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\BaseResource;

class BasicCampaignResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $campaign = $this->resource;
        $data = $campaign->attributesToArray();
        $appends = [];

        foreach ($campaign->getAppends() as $append) {
            $appends[$append] = $data[$append] ?? null;
            unset($data[$append]);
        }

        $appends['image_full_url'] = $campaign->image_full_url;

        if ($campaign->start_date) {
            $data['available_date_starts'] = $campaign->start_date->format('Y-m-d');
            unset($data['start_date']);
        }

        if ($campaign->end_date) {
            $data['available_date_ends'] = $campaign->end_date->format('Y-m-d');
            unset($data['end_date']);
        }

        foreach ($appends as $key => $value) {
            $data[$key] = $value;
        }

        return $data + $campaign->relationsToArray();
    }
}
