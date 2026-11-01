<?php

namespace App\Http\Resources\Vendor\Subscription;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TransactionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), array_merge(
            $this->resource->attributesToArray(),
            [
                'store' => $this->identity($this->resource->relationLoaded('store') ? $this->resource->store : null, 'name'),
                'package' => $this->identity($this->resource->relationLoaded('package') ? $this->resource->package : null, 'package_name'),
            ]
        ));
    }

    private function identity(mixed $model, string $nameColumn): ?array
    {
        return $model ? ['id' => $model->id, $nameColumn => $model->{$nameColumn}] : null;
    }
}
