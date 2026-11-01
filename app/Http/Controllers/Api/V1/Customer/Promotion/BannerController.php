<?php

namespace App\Http\Controllers\Api\V1\Customer\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Customer\Promotion\BannerResource;
use App\Http\Resources\Customer\Promotion\StoreBannerResource;
use App\Services\Marketing\BannerService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly BannerService $bannerService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->resolveCurrentModule($request);
        $this->applyZoneIds($request);

        $filters = $this->filters($request);

        if (service_api_module_active()) {
            return $this->bannerPayload(
                $this->bannerService->getServiceModuleBanners($filters),
                $this->bannerService->getServiceModuleCampaigns($filters)
            );
        }

        if (! config('module.current_module_data') && addon_published_status('Service')) {
            return $this->bannerPayload(
                $this->bannerService->getAllModuleBanners($filters),
                $this->bannerService->getAllModuleCampaigns($filters)
            );
        }

        try {
            return $this->bannerPayload(
                $this->bannerService->getZoneBanners($filters),
                $this->bannerService->getRunningCampaigns($filters)
            );
        } catch (\Exception $e) {
            return $this->bannerPayload([], []);
        }
    }

    public function listForStore(Request $request, mixed $storeId): JsonResponse
    {
        $this->applyZoneIds($request);

        $banners = $this->bannerService->getStoreBannerList(
            $storeId,
            $this->filters($request),
            ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => StoreBannerResource::collection($banners),
            'pagination' => $this->paginateFormatter($banners),
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'zone_id' => $request->header('zoneId'),
            'zone_ids' => $this->zoneIds($request),
            'module_id' => $this->currentModuleId(),
            'all_zone_service' => $this->currentModuleServesAllZones(),
            'featured' => $request->query('featured'),
            'locale' => app()->getLocale(),
            'customer_id' => auth('api')->check() ? auth('api')->id() : null,
        ];
    }

    private function bannerPayload(array $banners, array $campaigns): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), [
            'campaigns' => $campaigns,
            'banners' => BannerResource::collection($banners),
        ]);
    }

    private function resolveCurrentModule(Request $request): void
    {
        if (config('module.current_module_data') || ! $request->hasHeader('moduleId')) {
            return;
        }

        $module = getModule($request->header('moduleId'));

        if ($module) {
            config(['module.current_module_data' => $module]);
        }
    }
}
