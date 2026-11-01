<?php

namespace App\Http\Resources\Vendor\Item;

use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\BaseResource;

class PendingItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->attributesToArray();
        $names = $this->resource->resolved_category_names;

        unset($data['resolved_category_names']);

        $data['category_ids'] = collect(Helpers::decodeJsonToArray($this->resource->category_ids))
            ->map(fn ($value) => [
                'id' => (string) data_get($value, 'id'),
                'position' => data_get($value, 'position'),
                'name' => $names?->get((string) data_get($value, 'id')) ?? 'NA',
            ])
            ->all();

        return $data + $this->resource->relationsToArray();
    }
}
