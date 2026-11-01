<?php

namespace App\Http\Resources\Common\Delivery;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * The coverage a client must offer for a (zone, module).
 *
 * `coverage` is deliberately EMPTY for distance_wise and fixed_amount — those price the whole
 * zone and there is nothing to pick (§14.3). An empty array is the answer, not a missing one, so
 * a client can branch on `type` and render no picker rather than treating it as an error.
 *
 * `type` is null when the zone/module has no active rule at all.
 */
class CoverageResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'type' => $this->resource['type'],
            'coverage' => $this->resource['coverage'],
        ]);
    }
}
