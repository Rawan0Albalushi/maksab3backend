<?php

namespace App\Http\Resources\Common\System;

use App\Http\Resources\BaseResource;
use App\Services\System\MeasurementUnitService;
use Illuminate\Http\Request;

/**
 * ONE measurement unit, in the one shape every surface reports it in.
 *
 * Distance, weight and dimension are three independent settings (parcel brief decision 4) that
 * used to be reported three different ways: `/config` emitted a flat `distance_unit` pair, the
 * admin screens read `MeasurementUnitService` directly, and nothing at all told a client what
 * unit a weight band or a dimension class was written in — so apps hard-coded "KG" and "in".
 *
 * This is that single shape. Build the payload with
 * `app(MeasurementUnitService::class)->descriptor(...)` and render it here; do not assemble the
 * keys by hand at a call site, or the shape drifts again.
 *
 *   type       distance | weight | dimension
 *   unit       the configured enum value — km, mi, kg, lb, cm, in
 *   label      the symbol to suffix a number with. Equal to `unit`, and separate on purpose:
 *              a client renders `label` and matches on `unit`, so a future display change
 *              cannot break a client that branches on the enum.
 *   name       the translated full name — "Kilogram"
 *   supported  every value this setting accepts, so a client can validate or offer a switch
 *              without carrying the list itself
 *
 * NOTE FOR API CONSUMERS: a unit switch does NOT convert stored numbers. A band saved as
 * `0 - 2` under kilograms still reads `0 - 2` under pounds — its meaning changes, not its value
 * (parcel brief §0b). Render whatever `label` says beside the number you were given; never
 * convert one into the other.
 */
class UnitResource extends BaseResource
{
    /** The type this resource was handed, so a caller can pass a type string instead of an array. */
    public static function forType(string $type): self
    {
        return new self(app(MeasurementUnitService::class)->descriptor($type));
    }

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'type' => $this->resource['type'] ?? null,
            'unit' => $this->resource['unit'] ?? null,
            'label' => $this->resource['label'] ?? null,
            'name' => $this->resource['name'] ?? null,
            'supported' => $this->resource['supported'] ?? [],
        ]);
    }
}
