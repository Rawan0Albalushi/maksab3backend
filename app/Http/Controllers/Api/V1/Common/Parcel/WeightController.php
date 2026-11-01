<?php

namespace App\Http\Controllers\Api\V1\Common\Parcel;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Common\Parcel\ParcelTierListRequest;
use App\Http\Resources\Common\Parcel\WeightResource;
use App\Http\Resources\Common\System\UnitResource;
use App\Services\Parcel\WeightService;
use App\Services\System\MeasurementUnitService;
use Illuminate\Http\JsonResponse;

/**
 * GET parcel-weight — the weight bands a parcel checkout may offer for one (zone, module).
 *
 * PARCEL ONLY. The bands exist to price a parcel and nothing else selects one, so a non-parcel
 * (zone, module) answers with an empty list — not by refusing the call. That falls out of the
 * gate rather than being tested for here: `DeliveryRuleService::syncParcelTiers()` forces
 * `weight_charge_status` to false on any rule that does not connect a parcel-capable module, so
 * a food rule can never report a band even if one is posted at it.
 *
 * The two gates and the empty-list contract live in `WeightService::bandsForZoneModule()`.
 *
 * `status` is what a client branches on: false means this (zone, module) does not price by
 * weight, so render no picker. An empty `data` with `status` true means the platform has no
 * active band configured yet — a different problem, and one the admin fixes rather than the app.
 *
 * New endpoint with no shipped clients, so it is not paginated: the list is a handful of bands
 * chosen from in one dropdown, the same shape `delivery-charge/coverage-list` returns.
 */
class WeightController extends BaseApiController
{
    public function __construct(
        private readonly WeightService $weightService,
    ) {
    }

    public function index(ParcelTierListRequest $request): JsonResponse
    {
        $filters = $request->filters();

        $tier = $this->weightService->bandsForZoneModule(
            $filters['zone_id'],
            $filters['module_id'],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'status' => $tier['status'],
            // Reported even when the tier is off, so a client can label a band it already holds
            // — and so every surface reads the unit from one shape (MeasurementUnitService).
            'unit' => UnitResource::forType(MeasurementUnitService::TYPE_WEIGHT)->render(),
            'data' => WeightResource::renderCollection($tier['items']),
        ]);
    }
}
