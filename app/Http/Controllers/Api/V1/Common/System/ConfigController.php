<?php

namespace App\Http\Controllers\Api\V1\Common\System;

use App\Http\Requests\Common\System\ConfigRequest;
use App\Traits\Api\CachesApiPayloadTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\System\AnalyticScriptResource;
use App\Http\Resources\Common\System\PageMetaResource;
use App\Services\Chat\AutomatedMessageService;
use App\Services\Customer\UserService;
use App\Services\Payment\SettingService;
use App\Services\System\AnalyticScriptService;
use App\Services\System\BusinessSettingService;
use App\Services\System\ConfigService;
use App\Services\System\CurrencyService;
use App\Services\System\DataSettingService;
use App\Services\System\ModuleService;
use App\Services\System\PageSeoDataService;
use App\Services\System\SocialMediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Rental\Services\Vehicle\VehicleService;
use Modules\RideShare\Services\RideRequestService;
use Modules\TaxModule\Services\CalculateTaxService;
use Modules\TaxModule\Services\SystemTaxSetupService;

class ConfigController extends BaseApiController
{
    use CachesApiPayloadTrait;

    private const POLICY_STATUS_KEYS = ['refund_policy_status', 'cancellation_policy_status', 'shipping_policy_status'];

    public function __construct(
        private readonly ConfigService $configService,
        private readonly AnalyticScriptService $analyticScriptService,
        private readonly PageSeoDataService $pageSeoDataService,
        private readonly BusinessSettingService $businessSettingService,
        private readonly DataSettingService $dataSettingService,
        private readonly CurrencyService $currencyService,
        private readonly ModuleService $moduleService,
        private readonly SocialMediaService $socialMediaService,
        private readonly SettingService $settingService,
        private readonly AutomatedMessageService $automatedMessageService,
        private readonly UserService $userService,
    ) {
    }

    public function index(ConfigRequest $request): JsonResponse
    {
        // The zone and module the client is asking about. `/config` has always been scoped by
        // these headers; it simply never needed to read them until free delivery moved to a
        // per-(zone, module) setup. They resolve in the request and arrive here as scalars.
        $zoneIds = $request->zoneIds();
        $moduleId = $request->moduleId();

        $payload = $this->cachedPayload(
            'api.config',
            $request,
            ['module_id' => $moduleId],
            fn () => $this->configService->getAppConfiguration($this->platformData(), $zoneIds, $moduleId),
        );

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function analyticScripts(Request $request): JsonResponse
    {
        $scripts = $this->analyticScriptService->getActiveList(
            ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => AnalyticScriptResource::collection($scripts),
            'pagination' => $this->paginateFormatter($scripts),
        ]);
    }

    public function pageMetaData(Request $request): JsonResponse
    {
        $meta = $this->pageSeoDataService->findForPage($request->query('page_name'));

        return $this->responseFormatter(
            config('response.default_200'),
            $meta ? new PageMetaResource($meta) : null,
        );
    }

    private function platformData(): array
    {
        $policyStatuses = [];
        foreach (self::POLICY_STATUS_KEYS as $key) {
            $policyStatuses[$key] = $this->dataSettingService->findStatusValue($key);
        }

        return [
            'settings' => $this->businessSettingService->getAppConfigSettings(),
            'storage_disks' => [
                'logo_storage' => $this->businessSettingService->findStorageDisk('logo'),
                'icon_storage' => $this->businessSettingService->findStorageDisk('icon'),
            ],
            'app_download_links' => $this->dataSettingService->getUserAppDownloadLinks(),
            'currency_symbol' => $this->currencyService->findConfiguredSymbol(),
            'module' => $this->moduleService->findSoleActive(),
            'social_media' => $this->socialMediaService->getActive(),
            'has_active_sms' => $this->settingService->hasActiveSmsGateway(),
            'maintenance_mode' => $this->dataSettingService->getMaintenanceMode(),
            'policy_statuses' => $policyStatuses,
            'vehicle_minimums' => addon_published_status('Rental')
                ? app(VehicleService::class)->getMinimumPrices()
                : ['distance' => 0, 'hourly' => 0, 'day_wise' => 0],
            'system_tax' => addon_published_status('TaxModule')
                ? app(SystemTaxSetupService::class)->findDefault()
                : null,
            'ride_share' => addon_published_status('RideShare') ? $this->rideShareData() : [],
            'service_module' => addon_published_status('Service') ? $this->serviceModuleData() : [],
        ];
    }

    private function rideShareData(): array
    {
        $rideCounts = app(RideRequestService::class)->getTopCustomerRideCounts();

        return [
            'configs' => $this->dataSettingService->getRideShareConfigs(),
            'vat' => CalculateTaxService::getTaxPercentage('ride_module'),
            'rider_faqs' => $this->automatedMessageService->getRiderFaqs(),
            'page_rows' => $this->dataSettingService->getRideSharePageRows(),
            'top_customer_ride_counts' => $rideCounts,
            'top_customers' => $this->userService->getByIdsWithStorage($rideCounts->keys()),
            'total_customers' => $this->userService->countAll(),
        ];
    }

    private function serviceModuleData(): array
    {
        return [
            'settings' => $this->dataSettingService->getServiceModuleSettings(),
            'tax_setup' => app(SystemTaxSetupService::class)->findActiveByTaxPayer('service_provider'),
            'tax_percentage' => CalculateTaxService::getTaxPercentage('service_provider'),
        ];
    }
}
