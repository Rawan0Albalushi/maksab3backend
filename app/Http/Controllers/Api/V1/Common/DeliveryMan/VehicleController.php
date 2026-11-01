<?php

namespace App\Http\Controllers\Api\V1\Common\DeliveryMan;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Common\DeliveryMan\ExtraChargeRequest;
use App\Http\Resources\Common\DeliveryMan\VehicleResource;
use App\Services\DeliveryMan\DmVehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends BaseApiController
{
    public function __construct(
        private readonly DmVehicleService $dmVehicleService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $vehicles = $this->dmVehicleService->getActiveList(
            ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => VehicleResource::collection($vehicles),
            'pagination' => $this->paginateFormatter($vehicles),
        ]);
    }

    public function extraCharge(ExtraChargeRequest $request): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            $this->dmVehicleService->coverageCharge($request->distance()),
        );
    }
}
