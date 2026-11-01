<?php

namespace App\Http\Requests\Common\Parcel;

use App\Http\Requests\BaseRequest;

/**
 * GET parcel-weight / parcel-dimension — the two additive parcel tiers, for one (zone, module).
 *
 * One request for both endpoints because the input IS the same question: which (zone, module) is
 * being quoted for. Splitting it into two identical classes would leave two places to keep in
 * step and no second rule to justify either.
 *
 * Shaped after `Common\Delivery\CoverageRequest`, which asks the same pair for the same reason —
 * the module id comes from the header like everywhere else in this API and is resolved HERE, so
 * the service never reads a header (architecture rule 2).
 *
 * `zone_id` is required rather than taken from the `zoneId` header: that header carries a LIST
 * where zones overlap, and a delivery rule is looked up for exactly one zone. A client resolves
 * its zone first (`config/get-zone-id`) and passes the one it is checking out in.
 */
class ParcelTierListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'zone_id' => 'required|integer|exists:zones,id',
            'module_id' => 'nullable|integer|exists:modules,id',
        ];
    }

    public function filters(): array
    {
        return [
            'zone_id' => (int) $this->input('zone_id'),
            'module_id' => (int) ($this->input('module_id') ?: $this->header('moduleId')),
        ];
    }
}
