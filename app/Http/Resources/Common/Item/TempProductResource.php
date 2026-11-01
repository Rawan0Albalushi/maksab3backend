<?php

namespace App\Http\Resources\Common\Item;

class TempProductResource extends ProductResource
{
    private const RESOLVED_TAXONOMIES = [
        'nutritions' => 'resolved_nutritions',
        'allergies' => 'resolved_allergies',
        'generic' => 'resolved_generics',
    ];

    protected bool $tempProduct = true;

    protected function taxonomyNames(string $relation, string $column): array
    {
        return collect($this->resource->{self::RESOLVED_TAXONOMIES[$relation]} ?? [])
            ->pluck($column)
            ->values()
            ->all();
    }
}
