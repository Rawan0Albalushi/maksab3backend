<?php

namespace App\Http\Controllers\Api\V1\Customer\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Customer\Promotion\AdvertisementResource;
use App\Services\Marketing\AdvertisementService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertisementController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly AdvertisementService $advertisementService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $filters = $this->filters($request);

        $paginate = ['per_page' => $this->perPage($request), 'page' => $this->page($request)];

        $advertisements = service_api_module_active()
            ? $this->advertisementService->getServiceModulePaginatedList($filters, $paginate)
            : $this->advertisementService->getRunningPaginatedList($filters, $paginate);

        return $this->responseFormatter(config('response.default_200'), [
            'data' => AdvertisementResource::collection($advertisements),
            'pagination' => $this->paginateFormatter($advertisements),
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'zone_ids' => $this->zoneIds($request),
            'module_id' => $this->currentModuleId(),
            'locale' => app()->getLocale(),
            'customer_id' => auth('api')->check() ? auth('api')->id() : null,
        ];
    }
}
