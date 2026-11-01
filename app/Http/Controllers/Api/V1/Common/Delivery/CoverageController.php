<?php

namespace App\Http\Controllers\Api\V1\Common\Delivery;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Common\Delivery\CoverageRequest;
use App\Http\Resources\Common\Delivery\CoverageResource;
use App\Services\Zone\DeliveryRuleService;
use Illuminate\Http\JsonResponse;

/**
 * Coverage lookup — the areas or ZIP codes a customer chooses between before a fee can be quoted.
 *
 * New endpoint with no shipped clients, so it is REST-correct from the start: a GET on a plural
 * noun, returning the base-system envelope.
 */
class CoverageController extends BaseApiController
{
    public function __construct(
        private readonly DeliveryRuleService $deliveryRuleService,
    ) {
    }

    public function index(CoverageRequest $request): JsonResponse
    {
        $filters = $request->filters();

        $coverage = $this->deliveryRuleService->coverageForZone(
            $filters['zone_id'],
            $filters['module_id'],
        );

        return $this->responseFormatter(config('response.default_200'), new CoverageResource($coverage));
    }
}
