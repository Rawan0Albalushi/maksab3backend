<?php

namespace App\Http\Resources\Common\Parcel;

use App\Http\Resources\BaseResource;
use App\Models\Dimension;
use App\Models\Weight;
use App\Services\System\MeasurementUnitService;
use Illuminate\Http\Request;

/**
 * The weight band or size class an order was charged by, in one shape for every surface.
 *
 * Customer and deliveryman order details both render this; the admin blade reads the same
 * `band_label` / `size_label` accessors underneath it. So the three cannot describe the same
 * parcel differently, which is exactly what happened with the setup screens before the labels
 * moved onto the models.
 *
 * Built with `forWeight()` / `forDimension()` rather than a plain constructor, because the two
 * carry different measurements and only the factory knows which keys to read. What they share is
 * the envelope — `id`, `name`, `label`, `unit` — and that shared envelope is the point: a client
 * that only wants to print a line renders `label` and never branches on which tier it is.
 *
 * ALWAYS RENDER THE `label`, never rebuild it from the numbers. A band saved as `0 - 2` means two
 * kilograms or two pounds depending on `weight_unit`, and the value does not change when the
 * setting does (parcel brief §0b). `label` is the only form that carries the unit with it.
 *
 * Null in — null out. A non-parcel order, a parcel placed before the tiers were recorded, and a
 * parcel in a zone that does not price by this tier all have nothing to show, and the caller
 * renders nothing rather than an empty row.
 */
class OrderParcelTierResource extends BaseResource
{
    public static function forWeight(?Weight $weight): ?array
    {
        if (! $weight) {
            return null;
        }

        return [
            'id' => $weight->id,
            'name' => $weight->name,
            'label' => $weight->band_label,
            'from_weight' => (float) $weight->from_weight,
            'to_weight' => (float) $weight->to_weight,
            'unit' => app(MeasurementUnitService::class)->weightUnitLabel(),
        ];
    }

    public static function forDimension(?Dimension $dimension): ?array
    {
        if (! $dimension) {
            return null;
        }

        return [
            'id' => $dimension->id,
            'name' => $dimension->name,
            'label' => $dimension->size_label,
            'max_length' => (float) $dimension->max_length,
            'max_width' => (float) $dimension->max_width,
            'max_height' => (float) $dimension->max_height,
            'unit' => app(MeasurementUnitService::class)->dimensionUnitLabel(),
        ];
    }

    public function toArray(Request $request): array
    {
        return (array) $this->resource;
    }
}
