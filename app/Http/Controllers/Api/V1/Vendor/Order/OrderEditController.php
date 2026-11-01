<?php

namespace App\Http\Controllers\Api\V1\Vendor\Order;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Order\OrderEditLogRequest;
use App\Http\Requests\Vendor\Order\OrderUpdateRequest;
use App\Http\Resources\Vendor\Order\OrderEditItemResource;
use App\Http\Resources\Vendor\Order\OrderEditLogResource;
use App\Http\Resources\Vendor\Order\OrderResource;
use App\Services\Order\EtaService;
use App\Services\Item\ItemService;
use App\Services\Order\OrderEditLogService;
use App\Services\Order\OrderService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderEditController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderEditLogService $orderEditLogService,
        private readonly ItemService $itemService
    ) {}

    public function searchItems(Request $request): JsonResponse
    {
        $storeId = $this->vendorStoreId($request);

        if ($request->input('store_id') && (int) $request->input('store_id') !== (int) $storeId) {
            return $this->pagedResponse($this->emptyPaginator($this->perPage($request), $this->page($request)), OrderEditItemResource::class);
        }

        return $this->pagedResponse(
            $this->itemService->getStoreSearchList(
                ['store_id' => $storeId, 'keyword' => $request->input('keyword')],
                $this->pageParams($request)
            ),
            OrderEditItemResource::class
        );
    }

    public function logs(OrderEditLogRequest $request): JsonResponse
    {
        $order = $this->orderService->findVendorEditable($request->input('order_id'), $this->vendorFilters($request));

        if (! $order) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'order_id');
        }

        return $this->pagedResponse(
            $this->orderEditLogService->getList(['order_id' => $order->id], $this->pageParams($request)),
            OrderEditLogResource::class
        );
    }

    public function update(OrderUpdateRequest $request): JsonResponse
    {
        $order = $this->orderService->findVendorEditable($request->input('order_id'), $this->vendorFilters($request), withDetails: true);

        if (! $order) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'order_id');
        }

        if (! $order->is_editable) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.Order can not be edited'), 'status');
        }

        $result = $this->orderService->updateFromCart($order, $request->payload(), 'vendor');

        if (($result['status'] ?? 403) !== 200) {
            return $this->errorResponse(
                $this->statusConfig((int) ($result['status'] ?? 403)),
                $result['message'] ?? null,
                $result['code'] ?? 'order'
            );
        }

        return $this->responseFormatter(
            ['message' => $result['message']] + config('response.default_update_200'),
            new OrderResource(app(EtaService::class)->attach($this->orderService->findForEditResponse($order->id)))
        );
    }

    private function vendorFilters(Request $request): array
    {
        return ['vendor_id' => $request['vendor']?->id];
    }
}
