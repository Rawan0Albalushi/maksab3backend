<?php

namespace App\Http\Controllers\Api\V1\Vendor\Order;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Order\CompletedOrderRequest;
use App\Http\Requests\Vendor\Order\OrderAmountRequest;
use App\Http\Requests\Vendor\Order\OrderIdRequest;
use App\Http\Requests\Vendor\Order\OrderStatusRequest;
use App\Http\Resources\Common\Order\OrderDetailResource;
use App\Http\Resources\Common\Order\ParcelOrderResource;
use App\Http\Resources\Vendor\Order\OrderResource;
use App\Services\Order\OrderService;
use App\Traits\Api\ApiRequestContextTrait;
use App\Services\Order\EtaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly EtaService $etaService,
    ) {}

    public function current(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->etaService->attach($this->orderService->getVendorCurrentList($this->vendorFilters($request), $this->pageParams($request))),
            OrderResource::class
        );
    }

    public function index(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->etaService->attach($this->orderService->getVendorAllList($this->vendorFilters($request), $this->pageParams($request))),
            OrderResource::class
        );
    }

    public function completed(CompletedOrderRequest $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->orderService->getVendorCompletedList(
                $this->vendorFilters($request) + ['status' => $request->input('status')],
                $this->pageParams($request)
            ),
            OrderResource::class
        );
    }

    public function canceled(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->orderService->getVendorCanceledList($this->vendorFilters($request), $this->pageParams($request)),
            OrderResource::class
        );
    }

    public function show(OrderIdRequest $request): JsonResponse
    {
        $order = $this->orderService->findForVendor(
            $request->input('order_id'),
            $this->vendorFilters($request),
            ['customer', 'details', 'delivery_man', 'payments', 'orderProDiscount', 'storage', 'module', 'store.storage', 'store.store_sub']
        );

        if (! $order) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'order_id');
        }

        return $this->responseFormatter(config('response.default_200'), new OrderResource($this->etaService->attach($order)));
    }

    public function details(OrderIdRequest $request): JsonResponse
    {
        $result = $this->orderService->findVendorOrderDetails($request->input('order_id'), $this->vendorFilters($request));

        if ($result['status_code'] !== 200) {
            return $this->serviceResponse($result);
        }

        $order = $result['order'];
        $extras = [
            'is_guest' => (int) $order->is_guest,
            'pro_discount' => (float) ($order->orderProDiscount?->amount_saved ?? 0),
            'is_editable' => $order->is_editable,
        ];

        if ($result['details']) {
            return $this->responseFormatter(config('response.default_200'), OrderDetailResource::renderList(
                $result['details'],
                $this->orderService->detailImages($result['details']),
                $extras
            ));
        }

        return $this->responseFormatter(
            config('response.default_200'),
            (new ParcelOrderResource($order, $extras, ['store', 'orderProDiscount', 'order_pro_discount', 'payments']))->toArray($request)
        );
    }

    public function updateStatus(OrderStatusRequest $request): JsonResponse
    {
        return $this->serviceResponse(
            $this->orderService->updateStatusForVendor($request->payload(), $request->input('vendor')),
            config('response.default_update_200')
        );
    }

    public function updateAmount(OrderAmountRequest $request): JsonResponse
    {
        return $this->serviceResponse(
            $this->orderService->updateAmountForVendor($request->payload(), $request->input('vendor')),
            config('response.default_update_200')
        );
    }

    public function sendOtp(OrderIdRequest $request): JsonResponse
    {
        return $this->serviceResponse(
            $this->orderService->sendOtpForVendor($request->input('order_id'), $request->input('vendor'))
        );
    }

    private function vendorFilters(Request $request): array
    {
        return [
            'vendor_id' => $this->vendorId($request),
            'sub_self_delivery' => (bool) $this->vendorStore($request)?->sub_self_delivery,
        ];
    }
}
