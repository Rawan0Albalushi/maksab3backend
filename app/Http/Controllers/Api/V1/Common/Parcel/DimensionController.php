<?php

namespace App\Http\Controllers\Api\V1\Common\Parcel;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Common\Parcel\ParcelTierListRequest;
use App\Http\Resources\Common\Parcel\DimensionResource;
use App\Http\Resources\Common\System\UnitResource;
use App\Services\Parcel\DimensionService;
use App\Services\System\MeasurementUnitService;
use Illuminate\Http\JsonResponse;

/**
 * GET parcel-dimension — the package size classes a parcel checkout may offer for one
 * (zone, module).
 *
 * The sibling of WeightController in every respect; see it for the contract. The two tiers are
 * independent switches, so a rule may price by weight and not by size, or the reverse, and a
 * client must call both.
 */
class DimensionController extends BaseApiController
{
    public function __construct(
        private readonly DimensionService $dimensionService,
    ) {
    }

    public function index(ParcelTierListRequest $request): JsonResponse
    {
        $filters = $request->filters();

        $tier = $this->dimensionService->sizesForZoneModule(
            $filters['zone_id'],
            $filters['module_id'],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'status' => $tier['status'],
            'unit' => UnitResource::forType(MeasurementUnitService::TYPE_DIMENSION)->render(),
            'data' => DimensionResource::renderCollection($tier['items']),
        ]);
    }
}
