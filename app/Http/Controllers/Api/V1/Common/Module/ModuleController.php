<?php

namespace App\Http\Controllers\Api\V1\Common\Module;

use App\Traits\Api\CachesApiPayloadTrait;
use App\Exceptions\ZoneModuleException;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Module\ModuleResource;
use App\Http\Resources\Common\Module\TopOfferResource;
use App\Services\System\ModuleService;
use App\Services\Zone\ZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends BaseApiController
{
    use CachesApiPayloadTrait;

    public function __construct(
        private readonly ModuleService $moduleService,
        private readonly ZoneService $zoneService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $zoneId = $request->query('zone_id');

        $payload = $this->cachedPayload('api.module', $request, ['zone_id' => $zoneId], function () use ($request, $zoneId) {
            $modules = $this->moduleService->getList(
                filters: [
                    'zone_ids' => $zoneId ? [$zoneId] : $this->resolveZoneIds($request),
                    'exclude_parcel' => (bool) $zoneId,
                ],
                paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
            );

            return [
                'data' => ModuleResource::collection($modules),
                'pagination' => $this->paginateFormatter($modules),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function topOffer(Request $request): JsonResponse
    {
        $moduleId = $request->header('moduleId');

        if (empty($moduleId)) {
            return $this->moduleNotFound();
        }

        $module = $this->moduleService->findActiveWithOffer($moduleId, $this->resolveZoneIds($request));

        return $module
            ? $this->responseFormatter(config('response.default_200'), new TopOfferResource($module))
            : $this->moduleNotFound();
    }

    private function moduleNotFound(): JsonResponse
    {
        return $this->responseFormatter(config('response.default_404'), errors: [
            ['code' => 'module', 'message' => translate('No data found')],
        ]);
    }

    private function resolveZoneIds(Request $request): array
    {
        $header = $request->header('zoneId');

        if (empty($header)) {
            $zoneId = $this->zoneService->defaultZoneId();

            if (! $zoneId) {
                throw new ZoneModuleException(translate('No zone is available'));
            }

            $header = json_encode([$zoneId]);
            $request->headers->set('zoneId', $header);
        }

        $decoded = json_decode($header, true);

        return is_array($decoded) ? $decoded : [$decoded];
    }
}
