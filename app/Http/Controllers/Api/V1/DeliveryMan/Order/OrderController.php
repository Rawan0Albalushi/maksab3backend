<?php

namespace App\Http\Controllers\Api\V1\DeliveryMan\Order;

use App\Support\Settings\BusinessRules;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\DeliveryMan\Order\OrderAcceptRequest;
use App\Http\Requests\DeliveryMan\Order\OrderIdRequest;
use App\Http\Requests\DeliveryMan\Order\OrderListRequest;
use App\Http\Requests\DeliveryMan\Order\OrderPaymentStatusRequest;
use App\Http\Requests\DeliveryMan\Order\OrderStatusCountRequest;
use App\Http\Requests\DeliveryMan\Order\OrderStatusUpdateRequest;
use App\Http\Requests\DeliveryMan\Order\ParcelReturnDateRequest;
use App\Http\Requests\DeliveryMan\Order\ParcelReturnRequest;
use App\Http\Resources\Common\Order\OrderDetailResource;
use App\Http\Resources\DeliveryMan\Order\OrderResource;
use App\Http\Resources\DeliveryMan\Order\OrderStatusCountResource;
use App\Http\Resources\Common\Order\ParcelOrderResource;
use App\Services\Order\OrderService;
use App\Traits\Api\ApiRequestContextTrait;
use App\Services\Order\EtaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        protected OrderService $orderService,
        protected EtaService $etaService,
    ) {}

    public function index(OrderListRequest $request): JsonResponse
    {
        $orders = $this->orderService->getDeliveryManList(
            $request->filters() + ['delivery_man_id' => $this->deliveryManId()],
            $this->pageParams($request)
        );

        return $this->pagedResponse($this->etaService->attach($orders), OrderResource::class);
    }

    public function history(OrderListRequest $request): JsonResponse
    {
        $orders = $this->orderService->getDeliveryManHistoryList(
            $request->filters() + ['delivery_man_id' => $this->deliveryManId()],
            $this->pageParams($request)
        );

        return $this->pagedResponse($this->etaService->attach($orders), OrderResource::class);
    }

    public function statusStatistics(OrderStatusCountRequest $request): JsonResponse
    {
        $counts = $this->orderService->deliveryManStatusCounts([
            'delivery_man_id' => $this->deliveryManId(),
            'type' => $request->input('type'),
        ]);

        return $this->responseFormatter(
            config('response.default_200'),
            OrderStatusCountResource::collection($counts)->toArray($request)
        );
    }

    public function latest(Request $request): JsonResponse
    {
        $orders = $this->orderService->getDeliveryManAssignableList($this->deliveryMan(), $this->pageParams($request));

        return $this->pagedResponse($this->etaService->attach($orders), OrderResource::class);
    }

    public function show(OrderIdRequest $request): JsonResponse
    {
        $order = $this->orderService->findForDeliveryMan([
            'order_id' => $request->input('order_id'),
            'delivery_man_id' => $this->deliveryManId(),
        ]);

        if (! $order) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'order');
        }

        return $this->responseFormatter(
            config('response.default_200'),
            (new OrderResource($this->etaService->attach($order)))->toArray($request),
        );
    }

    public function details(OrderIdRequest $request): JsonResponse
    {
        $order = $this->orderService->findDetailForDeliveryMan([
            'order_id' => $request->input('order_id'),
            'delivery_man_id' => $this->deliveryManId(),
        ]);

        if (! $order) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'order');
        }

        if ($order->details->isNotEmpty()) {
            return $this->responseFormatter(config('response.default_200'), OrderDetailResource::renderList(
                $order->details,
                $this->orderService->detailImages($order->details),
                ['is_guest' => (int) $order->is_guest],
                ['vendor_id' => $order->store?->vendor_id]
            ));
        }

        if ($order->order_type === 'parcel') {
            return $this->responseFormatter(config('response.default_200'), (new ParcelOrderResource($order))->toArray($request));
        }

        if ($order->prescription_order == 1) {
            return $this->responseFormatter(config('response.default_200'), []);
        }

        return $this->errorResponse(config('response.default_404'), translate('No data found'), 'order');
    }

    public function accept(OrderAcceptRequest $request): JsonResponse
    {
        $result = $this->orderService->acceptForDeliveryMan($this->deliveryMan(), $request->payload());

        return $this->serviceResponse(
            $result,
            ['message' => translate('Order accepted successfully')] + config('response.default_update_200')
        );
    }

    public function updateStatus(OrderStatusUpdateRequest $request): JsonResponse
    {
        $order = $this->orderService->findStatusChangeable([
            'order_id' => $request->input('order_id'),
            'delivery_man_id' => $this->deliveryManId(),
        ]);

        if (! $order || (! $order->store && $order->order_type !== 'parcel')) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.You can not change the status of this order'), 'not_found');
        }

        $result = $this->orderService->updateStatusForDeliveryMan($this->deliveryMan(), $order, $request->payload());

        return $this->serviceResponse(
            $result,
            ['message' => translate('Status updated')] + config('response.default_update_200')
        );
    }

    public function updatePaymentStatus(OrderPaymentStatusRequest $request): JsonResponse
    {
        $updated = $this->orderService->markPaymentPaid([
            'order_id' => $request->input('order_id'),
            'delivery_man_id' => $this->deliveryManId(),
        ], $request->input('status'));

        if (! $updated) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'order');
        }

        return $this->responseFormatter(['message' => translate('Payment status updated')] + config('response.default_update_200'));
    }

    public function sendOtp(OrderIdRequest $request): JsonResponse
    {
        $deliveryMan = $this->deliveryMan();

        $order = $this->orderService->findForOtp(
            ['order_id' => $request->input('order_id'), 'delivery_man_id' => $deliveryMan->id],
            BusinessRules::deliverymanConfirmsOrder() && $deliveryMan->type === 'zone_wise'
        );

        if (! $order) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'order');
        }

        if (! $this->orderService->sendOtpToCustomer($order)) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.push_notification_failed'), 'otp');
        }

        return $this->responseFormatter(config('response.default_200'));
    }

    public function addReturnDate(ParcelReturnDateRequest $request): JsonResponse
    {
        $updated = $this->orderService->setParcelReturnDate(
            $request->input('order_id'),
            ['delivery_man_id' => $this->deliveryManId()],
            $request->input('return_date')
        );

        if (! $updated) {
            return $this->errorResponse(config('response.forbidden_403'), translate('No data found'), 'amount');
        }

        return $this->responseFormatter(['message' => translate('Added successfully')] + config('response.default_update_200'));
    }

    public function returnParcel(ParcelReturnRequest $request): JsonResponse
    {
        $order = $this->orderService->findReturnableParcel(
            $request->input('order_id'),
            ['delivery_man_id' => $this->deliveryManId()]
        );

        $failure = $this->orderService->validateParcelReturn($order, ['return_otp' => $request->input('return_otp')]);

        if (data_get($failure, 'status_code') === 403) {
            return $this->serviceResponse((array) $failure);
        }

        $this->orderService->returnParcel($order);

        return $this->responseFormatter(['message' => translate('messages.Parcel returned successfully')] + config('response.default_update_200'));
    }
}
