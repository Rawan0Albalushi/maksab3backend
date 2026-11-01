<?php

namespace App\Http\Controllers\Api\V1\Common\System;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Common\System\DistanceRequest;
use App\Http\Requests\Common\System\PlaceDetailsRequest;
use App\Http\Requests\Common\System\PlaceSearchRequest;
use App\Http\Requests\Common\System\RouteRequest;
use App\Http\Requests\Common\Zone\ZoneCoordinateRequest;
use App\Services\System\MapService;
use Illuminate\Http\JsonResponse;

class MapController extends BaseApiController
{
    public function __construct(
        private readonly MapService $mapService
    ) {
    }

    public function placeAutocomplete(PlaceSearchRequest $request): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            $this->mapService->searchPlaces($request->searchText(), app()->getLocale()),
        );
    }

    public function placeDetails(PlaceDetailsRequest $request): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            $this->mapService->findPlace($request->placeId()),
        );
    }

    public function geocode(ZoneCoordinateRequest $request): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            $this->mapService->resolveCoordinates($request->latitude(), $request->longitude()),
        );
    }

    public function distance(DistanceRequest $request): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            $this->mapService->routeMatrix($request->origin(), $request->destination(), $request->travelMode()),
        );
    }

    public function direction(RouteRequest $request): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            $this->mapService->computeRoute($request->origin(), $request->destination(), $request->travelMode()),
        );
    }
}
