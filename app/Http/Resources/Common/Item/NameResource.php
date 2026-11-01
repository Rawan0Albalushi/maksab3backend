<?php

namespace App\Http\Resources\Common\Item;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class NameResource extends BaseResource
{
    public function __construct(mixed $resource, private readonly string $nameColumn)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'name' => $this->resource->{$this->nameColumn},
        ]);
    }
}
