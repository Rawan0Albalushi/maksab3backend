<?php

namespace App\Http\Controllers\Api\V1\Customer\Promotion;

use App\Traits\Api\CachesApiPayloadTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Promotion\FlashSaleItemListRequest;
use App\Http\Resources\Customer\Promotion\FlashSaleItemResource;
use App\Http\Resources\Customer\Promotion\FlashSaleResource;
use App\Services\Marketing\FlashSaleItemService;
use App\Services\Marketing\FlashSaleService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlashSaleController extends BaseApiController
{
    use CachesApiPayloadTrait;

    use ApiRequestContextTrait;

    public function __construct(
        private readonly FlashSaleService $flashSaleService,
        private readonly FlashSaleItemService $flashSaleItemService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $payload = $this->cachedPayload('api.flash_sales', $request, [], function () use ($request) {
            $flashSale = $this->flashSaleService->getRunning([
                'zone_ids' => $this->zoneIds($request),
                'module_id' => $this->currentModuleId(),
                'customer_id' => auth('api')->id(),
            ]);

            return $flashSale ? new FlashSaleResource($flashSale) : null;
        });

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function items(FlashSaleItemListRequest $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $filters = $request->filters();
        $flashSale = $this->flashSaleService->findRunning($filters);

        if (! $flashSale) {
            return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'flash_sale', 'message' => translate('No data found')],
            ]);
        }

        $items = $this->flashSaleItemService->getList(
            filters: array_merge($filters, ['flash_sale_id' => $flashSale->id]),
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'flash_sale' => new FlashSaleResource($flashSale),
            'data' => FlashSaleItemResource::collection($items),
            'pagination' => $this->paginateFormatter($items),
        ]);
    }
}
