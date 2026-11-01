<?php

namespace Modules\TaxModule\Http\Resources\Common\Tax;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class TaxResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'name' => $this->resource->name,
            'tax_rate' => $this->resource->tax_rate,
        ]);
    }
}
