<?php

namespace App\Http\Controllers\Api\V1\Common\Marketing;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Marketing\AppDownloadResource;
use App\Http\Resources\Common\Marketing\FlutterLandingPageResource;
use App\Http\Resources\Common\Marketing\ReactLandingPageResource;
use App\Services\Marketing\FlutterSpecialCriteriaService;
use App\Services\Marketing\ReactPromotionalBannerService;
use App\Services\Marketing\ReactTestimonialService;
use App\Services\System\DataSettingService;
use App\Services\System\FaqService;
use App\Services\Zone\ZoneService;
use Illuminate\Http\JsonResponse;

class LandingPageController extends BaseApiController
{
    public function __construct(
        private readonly DataSettingService $dataSettingService,
        private readonly ReactTestimonialService $reactTestimonialService,
        private readonly ReactPromotionalBannerService $reactPromotionalBannerService,
        private readonly FlutterSpecialCriteriaService $flutterSpecialCriteriaService,
        private readonly FaqService $faqService,
        private readonly ZoneService $zoneService
    ) {
    }

    public function react(): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            new ReactLandingPageResource([
                'settings' => $this->dataSettingService->getReactLandingSettings(),
                'testimonials' => $this->reactTestimonialService->getAll(),
                'promotional_banners' => $this->reactPromotionalBannerService->getActiveImageUrls(),
                'zones' => $this->zoneService->getActiveWithModules(),
                'popular_client_images' => $this->dataSettingService->getValuesByKey('popular_client_image'),
                'faqs' => $this->faqService->getGeneral(),
            ]),
        );
    }

    public function flutter(): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            new FlutterLandingPageResource([
                'settings' => $this->dataSettingService->getFlutterLandingSettings(),
                'zones' => $this->zoneService->getActiveWithModules(),
                'special_criterias' => $this->flutterSpecialCriteriaService->getActive(),
            ]),
        );
    }

    public function appDownload(): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            new AppDownloadResource([
                'settings' => $this->dataSettingService->getAppDownloadSettings(),
            ]),
        );
    }
}
