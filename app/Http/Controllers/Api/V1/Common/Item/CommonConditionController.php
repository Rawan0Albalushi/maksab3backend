<?php

namespace App\Http\Controllers\Api\V1\Common\Item;

use App\Traits\Api\CachesApiPayloadTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Item\CommonConditionResource;
use App\Http\Resources\Common\Item\ItemResource;
use App\Services\Item\CommonConditionService;
use App\Services\Item\ItemService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommonConditionController extends BaseApiController
{
    use CachesApiPayloadTrait;

    use ApiRequestContextTrait;

    public function __construct(
        private readonly CommonConditionService $commonConditionService,
        private readonly ItemService $itemService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $payload = $this->cachedPayload('api.common_condition', $request, $this->filters($request), function () use ($request) {
            $conditions = $this->commonConditionService->getList(
                filters: $this->filters($request),
                paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
            );

            return [
                'data' => CommonConditionResource::collection($conditions),
                'pagination' => $this->paginateFormatter($conditions),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function options(Request $request): JsonResponse
    {
        $conditions = $this->commonConditionService->getActiveList(
            ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => CommonConditionResource::collection($conditions),
            'pagination' => $this->paginateFormatter($conditions),
        ]);
    }

    public function items(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $context = array_merge($this->filters($request), ['condition' => $request->route('condition_id')]);

        $payload = $this->cachedPayload('api.common_condition_items', $request, $context, function () use ($request, $context) {
            $items = $this->itemService->getCommonConditionList(
                filters: $context,
                paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
            );

            return [
                'data' => ItemResource::collection($items),
                'pagination' => $this->paginateFormatter($items),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    private function filters(Request $request): array
    {
        return [
            'zone_ids' => $this->zoneIds($request),
            'module_id' => $this->currentModuleId(),
            'type' => $request->query('type', 'all'),
        ];
    }
}
