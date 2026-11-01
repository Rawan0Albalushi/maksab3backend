<?php

namespace App\Http\Controllers\Api\V1\Common\Item;

use App\Traits\Api\CachesApiPayloadTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Item\BrandResource;
use App\Http\Resources\Common\Item\ItemResource;
use App\Services\Item\BrandService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends BaseApiController
{
    use CachesApiPayloadTrait;

    use ApiRequestContextTrait;

    public function __construct(
        private readonly BrandService $brandService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $payload = $this->cachedPayload('api.brand', $request, $this->filters($request), function () use ($request) {
            $brands = $this->brandService->getList(
                filters: $this->filters($request),
                paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
            );

            return [
                'data' => BrandResource::collection($brands),
                'pagination' => $this->paginateFormatter($brands),
            ];
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function items(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $items = $this->brandService->getBrandItems(
            filters: $this->itemFilters($request),
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ItemResource::collection($items),
            'pagination' => $this->paginateFormatter($items),
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'zone_ids' => $this->zoneIds($request) ?: null,
            'module_id' => getModuleId($request->header('moduleId')),
            'top' => $request->query('top'),
        ];
    }

    private function itemFilters(Request $request): array
    {
        return [
            'zone_ids' => $this->zoneIds($request),
            'module_id' => $this->currentModuleId(),
            'type' => $request->query('type', 'all'),
            'brand' => $request->route('brand_id'),
        ];
    }

}
