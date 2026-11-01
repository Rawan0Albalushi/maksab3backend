<?php

namespace App\Http\Controllers\Api\V1\Customer\Promotion;

use App\Traits\Api\CachesApiPayloadTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Promotion\CampaignDetailRequest;
use App\Http\Resources\Customer\Promotion\CampaignDetailResource;
use App\Http\Resources\Customer\Promotion\CampaignResource;
use App\Services\Marketing\CampaignService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends BaseApiController
{
    use CachesApiPayloadTrait;

    use ApiRequestContextTrait;

    public function __construct(
        private readonly CampaignService $campaignService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $payload = $this->cachedPayload('api.campaigns_basic', $request, $this->filters($request), function () use ($request) {
            $campaigns = $this->campaignService->getList(
                filters: $this->filters($request),
                paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
            );

            return [
                'data' => CampaignResource::collection($campaigns),
                'pagination' => $this->paginateFormatter($campaigns),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function show(CampaignDetailRequest $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $campaign = $this->campaignService->find($request->campaignId(), $request->filters());

        if (! $campaign) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(config('response.default_200'), new CampaignDetailResource($campaign));
    }

    private function filters(Request $request): array
    {
        return [
            'zone_ids' => $this->zoneIds($request),
            'module_id' => $this->currentModuleId(),
            'all_zone_service' => (bool) $this->currentModuleServesAllZones(),
        ];
    }

}
