<?php

namespace Modules\AI\app\Http\Resources\Vendor\Product;

use App\Http\Resources\BaseResource;

class GenerationResource extends BaseResource
{
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), $this->preserveShape($this->resource));
    }

    private function preserveShape(array $payload): array
    {
        $encoded = json_encode($payload);

        return $encoded === false ? $payload : (array) json_decode($encoded, false);
    }
}
