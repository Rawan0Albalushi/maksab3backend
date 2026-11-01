<?php

namespace App\Http\Controllers\Api\V1\Customer\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Customer\Promotion\ItemCampaignResource;
use App\Services\Marketing\ItemCampaignService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemCampaignController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly ItemCampaignService $itemCampaignService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $campaigns = $this->itemCampaignService->getList(
            filters: $this->filters($request),
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemCampaignResource::collection($campaigns),
            'pagination' => $this->paginateFormatter($campaigns),
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'zone_ids' => $this->zoneIds($request),
            'module_id' => $this->currentModuleId(),
            'customer_id' => auth('api')->id(),
        ];
    }

}
