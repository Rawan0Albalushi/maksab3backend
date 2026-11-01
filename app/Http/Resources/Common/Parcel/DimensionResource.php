<?php

namespace App\Http\Resources\Common\Parcel;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * One package size class, as the parcel checkout's picker needs it.
 *
 * The three maxima are the largest box the class accepts, written in whatever `dimension_unit`
 * names and never converted — see WeightResource for why. `charge` is the additive amount
 * selecting this class adds to the delivery fee.
 */
class DimensionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'max_length' => (float) $this->resource['max_length'],
            'max_width' => (float) $this->resource['max_width'],
            'max_height' => (float) $this->resource['max_height'],
            'charge' => (float) $this->resource['charge'],
        ]);
    }
}
