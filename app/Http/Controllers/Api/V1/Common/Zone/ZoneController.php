<?php

namespace App\Http\Controllers\Api\V1\Common\Zone;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Common\Zone\ZoneCheckRequest;
use App\Http\Requests\Common\Zone\ZoneCoordinateRequest;
use App\Http\Resources\Common\Zone\ZoneCoverageResource;
use App\Http\Resources\Common\Zone\ZoneResource;
use App\Services\Zone\ZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends BaseApiController
{
    public function __construct(
        private readonly ZoneService $zoneService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $zones = $this->zoneService->getActiveList(
            ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ZoneResource::collection($zones),
            'pagination' => $this->paginateFormatter($zones),
        ]);
    }

    public function check(ZoneCheckRequest $request): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            $this->zoneService->containsCoordinates(
                $request->zoneId(),
                $request->latitude(),
                $request->longitude(),
            ),
        );
    }
    public function resolve(ZoneCoordinateRequest $request): JsonResponse
    {
        $result = $this->zoneService->findByCoordinates($request->latitude(), $request->longitude());

        if ($result['containing']->isEmpty()) {
            return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'coordinates', 'message' => translate('messages.Service not available in this area')],
            ]);
        }

        if ($result['active']->isEmpty()) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'coordinates', 'message' => translate('messages.We are temporarily unavailable in this area')],
            ]);
        }

        return $this->responseFormatter(config('response.default_200'), [
            'zone_id' => json_encode($result['active']->pluck('id')->all()),
            'zone_data' => ZoneCoverageResource::collection($result['active']),
        ]);
    }
}
