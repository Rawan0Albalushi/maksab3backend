<?php

namespace App\Http\Resources\Common\Parcel;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * One weight band, as the parcel checkout's picker needs it.
 *
 * `from_weight` and `to_weight` are written in whatever `weight_unit` names and are NEVER
 * converted — a band saved as `0 - 2` under kilograms still reads `0 - 2` under pounds, its
 * meaning changing rather than its value (parcel brief §0b). The unit is reported once beside the
 * list, not per row, by `Common\System\UnitResource`.
 *
 * `charge` is what selecting this band ADDS to the delivery fee under the rule that priced the
 * list — model (a), the additive tier. 0.00 where the rule never priced the band.
 */
class WeightResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'from_weight' => (float) $this->resource['from_weight'],
            'to_weight' => (float) $this->resource['to_weight'],
            'charge' => (float) $this->resource['charge'],
        ]);
    }
}
