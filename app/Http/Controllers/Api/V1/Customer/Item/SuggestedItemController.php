<?php

namespace App\Http\Controllers\Api\V1\Customer\Item;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Item\ItemResource;
use App\Services\Item\ItemService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestedItemController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly ItemService $itemService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $items = $this->itemService->getSuggestedList(
            filters: [
                'zone_ids' => $this->zoneIds($request),
                'module_id' => $this->currentModuleId(),
                'interest' => Helpers::decodeJsonToArray($request->user()->interest),
            ],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemResource::collection($items),
            'pagination' => $this->paginateFormatter($items),
        ]);
    }
}
