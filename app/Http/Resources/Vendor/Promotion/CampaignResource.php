<?php

namespace App\Http\Resources\Vendor\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class CampaignResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $campaign = $this->resource;
        $storeId = (int) $request->input('vendor_store_id');
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

        if (count($campaign->translations) > 0) {
            $translated = array_column($campaign->translations->toArray(), 'value', 'key');
            $data['title'] = $translated['title'] ?? $data['title'] ?? null;
            $data['description'] = $translated['description'] ?? $data['description'] ?? null;
        }

        $data['vendor_status'] = $campaign->stores
            ->firstWhere(fn ($store) => (int) $store->pivot->store_id === $storeId)?->pivot->campaign_status;
        $data['is_joined'] = $campaign->stores->contains(fn ($store) => (int) $store->id === $storeId);

        foreach ($appends as $key => $value) {
            $data[$key] = $value;
        }

        return $data + array_diff_key($campaign->relationsToArray(), array_flip(['stores']));
    }
}
