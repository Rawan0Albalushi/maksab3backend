<?php

namespace App\Traits\Model;

use Illuminate\Database\Eloquent\Builder;

trait HasStorageTrait
{
    use HasStorageRelationTrait;

    public function scopeWithStorage(Builder $query): Builder
    {
        return $query->with('storage');
    }
}
