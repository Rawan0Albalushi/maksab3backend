<?php

namespace App\Http\Resources\Common\Zone;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ZoneResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'name' => $this->resource->name,
            // The customer-facing label. Both follow the request's locale through the model's
            // translated attributes and fall back to the stored default when a locale has none.
            'display_name' => $this->resource->display_name,
            'formated_coordinates' => $this->formatedCoordinates(),
            'coordinates' => $this->resource->coordinates,
            'delivery_options_matrix' => $this->deliveryOptionsMatrix(),
            'modules' => $this->whenLoaded('modules'),
        ]);
    }

    private function deliveryOptionsMatrix(): mixed
    {
        $matrix = $this->resource->getAttribute('delivery_options_matrix') ?? [];

        return count($matrix) ? (object) $matrix : [];
    }

    private function formatedCoordinates(): array
    {
        $area = json_decode($this->resource->coordinates[0]->toJson(), true);

        return array_map(
            fn ($coordinate) => (object) ['lat' => $coordinate[1], 'lng' => $coordinate[0]],
            $area['coordinates']
        );
    }
}
