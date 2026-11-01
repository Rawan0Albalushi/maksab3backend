<?php

namespace App\Services\System;

use App\Models\PriorityList;
use App\Services\BaseService;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Collection;

class PriorityListService extends BaseService
{
    public function getAll(): mixed
    {
        return PriorityList::select('name', 'value', 'type')->get();
    }

    /**
     * Every stored value keyed by "<name>|<type>", for callers that need many of them at once.
     *
     * The admin priority settings page renders 41 of these; looking them up one by one is 41
     * round trips. Cached under the same `priority_settings` group as the API lookups, so the
     * `priority_setting` tag busted by the PriorityList model invalidates both together.
     */
    public function keyedValues(): Collection
    {
        return ApiCache::remember(
            'priority_settings',
            'keyed_values',
            fn () => collect($this->getAll())->mapWithKeys(
                fn ($row) => [$row->name.'|'.$row->type => $row->value]
            )
        );
    }
}
