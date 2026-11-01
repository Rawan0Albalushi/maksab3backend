<?php

namespace App\Http\Controllers\Api\V1\Customer\Promotion;

use App\Traits\Api\CachesApiPayloadTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Customer\Promotion\ModuleBannerResource;
use App\Http\Resources\Customer\Promotion\ModuleBannerVideoResource;
use App\Services\Marketing\ModuleWiseBannerService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleBannerController extends BaseApiController
{
    use CachesApiPayloadTrait;

    use ApiRequestContextTrait;

    public function __construct(
        private readonly ModuleWiseBannerService $moduleWiseBannerService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        // A type with the admin page switched off (pharmacy) has no way left to edit or disable
        // what it saved before, so those rows stop being served instead of lingering in the app.
        // Same envelope and shape, just empty, so older app builds need no change.
        $banners = config('module.'.$this->currentModuleType().'.other_banners')
            ? $this->moduleWiseBannerService->getPromotionalBanners(
                filters: $this->filters(),
                paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
            )
            : $this->emptyPaginator($this->perPage($request), $this->page($request));

        return $this->responseFormatter(config('response.default_200'), new ModuleBannerResource([
            'banners' => $banners,
            'module_type' => $this->currentModuleType(),
            'pagination' => $this->paginateFormatter($banners),
        ]));
    }

    public function videoContent(Request $request): JsonResponse
    {
        return $this->cachedJson('api.other_banners_video', $request, [], fn () => $this->responseFormatter(
            config('response.default_200'),
            new ModuleBannerVideoResource($this->moduleWiseBannerService->getVideoContent($this->filters()))
        ));
    }

    private function filters(): array
    {
        return [
            'module_id' => $this->currentModuleId(),
        ];
    }

    private function currentModuleType(): mixed
    {
        return config('module.current_module_data')['module_type'] ?? null;
    }
}
