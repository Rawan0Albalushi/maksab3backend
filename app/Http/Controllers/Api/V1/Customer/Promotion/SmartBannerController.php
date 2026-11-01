<?php

namespace App\Http\Controllers\Api\V1\Customer\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Customer\Promotion\SmartBannerResource;
use App\Services\Marketing\SmartBannerService;
use App\Traits\Api\ApiRequestContextTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmartBannerController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly SmartBannerService $smartBannerService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $banners = $this->smartBannerService->getList(
            filters: $this->filters($request),
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => SmartBannerResource::collection($banners),
            'pagination' => $this->paginateFormatter($banners),
        ]);
    }

    private function filters(Request $request): array
    {
        $now = Carbon::now();

        return [
            'zone_ids' => $this->zoneIds($request),
            'today_date' => $now->toDateString(),
            'now_time' => $now->format('H:i:s'),
        ];
    }
}
