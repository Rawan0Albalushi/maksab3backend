<?php

namespace App\Http\Controllers\Api\V1\Customer\Order;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Order\GuestOrderRequest;
use App\Http\Requests\Customer\Order\OrderActionRequest;
use App\Http\Requests\Customer\Order\OrderCancelRequest;
use App\Http\Requests\Customer\Order\OrderIdRequest;
use App\Http\Requests\Customer\Order\OrderTrackRequest;
use App\Http\Requests\Customer\Order\ParcelReturnRequest;
use App\Http\Resources\Customer\Order\OrderActivityResource;
use App\Http\Resources\Customer\Order\OrderCardResource;
use App\Http\Resources\Customer\Order\OrderDetailResource;
use App\Services\Promotion\BogoOrderService;
use App\Services\Promotion\BundleOrderService;
use App\Http\Resources\Customer\Order\OrderParcelResource;
use App\Http\Resources\Customer\Order\OrderResource;
use App\Http\Resources\Customer\Order\OrderTrackResource;
use App\Http\Resources\Customer\Order\RideOrderResource;
use App\Http\Resources\Customer\Order\TripOrderResource;
use App\Services\Order\EtaService;
use App\Services\Order\OrderService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends BaseApiController
{
    use ApiRequestContextTrait;

    private const LIST_TYPES = ['previous', 'running', 'all'];

    private const CANCELLABLE_STATUSES = ['pending', 'failed', 'canceled'];

    public function __construct(
        private readonly OrderService $orderService,
        private readonly EtaService $etaService,
    ) {}

    public function index(GuestOrderRequest $request): JsonResponse
    {
        $filters = $this->listFilters($request);
        $paginate = $this->pageParams($request);

        return match ($this->orderService->getOrderType($filters['module_id'])) {
            'ride' => $this->moduleListResponse($this->orderService->getRideList($filters, $paginate), $this->orderService->rideStatusCounts($filters), $filters, RideOrderResource::class),
            'trip' => $this->moduleListResponse($this->orderService->getTripList($filters, $paginate), $this->orderService->tripStatusCounts($filters), $filters, TripOrderResource::class),
            default => $this->moduleListResponse($this->etaService->attach($this->orderService->getList($filters, $paginate)), $this->orderService->statusCounts($filters), $filters, OrderResource::class),
        };
    }

    public function runningOrders(GuestOrderRequest $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->etaService->attach($this->orderService->getRunningList($this->orderOwner($request), $this->pageParams($request))),
            OrderResource::class
        );
    }

    public function allRunningOrders(GuestOrderRequest $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->orderService->getActivityFeed($this->orderOwner($request), $this->pageParams($request)),
            OrderActivityResource::class
        );
    }

    public function show(OrderIdRequest $request): JsonResponse
    {
        $order = $this->orderService->findDetail(array_merge($this->orderOwner($request), ['order_id' => $request->input('order_id')]));

        if (! $order) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $saverDeliveryTime = $this->orderService->saverDeliveryWindow($order);

        if ($order->details->isNotEmpty()) {
            return $this->responseFormatter(config('response.default_200'), $this->detailRows($order, $saverDeliveryTime));
        }

        if ($order->order_type === 'parcel' || $order->prescription_order) {
            return $this->responseFormatter(
                config('response.default_200'),
                (new OrderParcelResource($order))->withSaverDeliveryTime($saverDeliveryTime)
            );
        }

        return $this->responseFormatter(config('response.default_404'));
    }


    public function track(OrderTrackRequest $request): JsonResponse
    {
        $order = $this->orderService->findForTracking([
            'order_id' => $request->input('order_id'),
            'user_id' => $request->user?->id,
            'contact_number' => $request->contactNumber(),
        ]);

        if (! $order) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(
            config('response.default_200'),
            (new OrderTrackResource($this->etaService->attach($order)))
                ->withSaverDeliveryTime($this->orderService->saverDeliveryWindow($order))
        );
    }

    public function destroy(OrderActionRequest $request): JsonResponse
    {
        $hidden = $this->orderService->hide(array_merge($this->orderOwner($request), [
            'order_ids' => (array) $request->input('order_id'),
        ]));

        if (! $hidden) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(config('response.default_delete_200'));
    }

    public function cancel(OrderCancelRequest $request): JsonResponse
    {
        $order = $this->orderService->findCancellable(array_merge($this->orderOwner($request), ['order_id' => $request->input('order_id')]));
        $payload = ['reason' => $request->input('reason'), 'note' => $request->input('note')];

        if (! $order) {
            return $this->responseFormatter(config('response.default_404'));
        }

        if ($order->order_type === 'parcel') {
            $result = $this->orderService->cancelParcel($order, $payload);

            // cancelParcelOrder() already words this one for itself -- plain, or the
            // "contact admin for refund" variant when a return fee was charged -- and that
            // was being thrown away in favour of the generic 200.
            return data_get($result, 'status_code') === 200
                ? $this->responseFormatter(
                    ['message' => data_get($result, 'message') ?: translate('messages.Order cancelled successfully')] + config('response.default_200')
                )
                : $this->errorResponse(config('response.forbidden_403'), data_get($result, 'message'), data_get($result, 'code'));
        }

        if (! in_array($order->order_status, self::CANCELLABLE_STATUSES, true)) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.You can not cancel after confirm'), 'order');
        }

        $this->orderService->cancel($order, $payload);

        return $this->responseFormatter(
            ['message' => translate('messages.Order cancelled successfully')] + config('response.default_200')
        );
    }

    public function parcelReturn(ParcelReturnRequest $request): JsonResponse
    {
        $order = $this->orderService->findReturnableParcel($request->input('order_id'));
        $failure = $this->orderService->validateParcelReturn($order, ['return_otp' => $request->input('return_otp')]);

        if ($failure) {
            return $this->errorResponse(config('response.forbidden_403'), data_get($failure, 'message'), data_get($failure, 'code'));
        }

        $this->orderService->returnParcel($order);

        return $this->responseFormatter(config('response.default_200'));
    }

    public function lastOrders(Request $request): JsonResponse
    {
        if (! auth('api')->id()) {
            return $this->pagedResponse($this->emptyPaginator($this->perPage($request), $this->page($request)), OrderCardResource::class);
        }

        $orders = $this->orderService->getRecentDeliveredList([
            'user_id' => auth('api')->id(),
            'module_id' => $request->header('moduleId'),
            'store_id' => $request->input('store_id'),
        ], $this->pageParams($request));

        // Reordering puts the order's own store back in the cart, and a store the customer has
        // since moved away from cannot deliver to them -- the cart refuses it at the next step.
        // Better to say so on the card than to offer a button that only fails.
        //
        // Decided here rather than in Order::can_reorder: which zones apply is request context
        // (the `zoneId` header), and a model accessor has no business reading one.
        //
        // zoneIds(), not applyZoneIds(): the latter falls back to the default zone when the
        // header is absent and throws when the header names no active zone, and neither belongs
        // on a listing. With no header there is no zone to judge by, so nothing is narrowed.
        $this->markStoreZoneReachable($orders->getCollection(), $this->zoneIds($request));

        return $this->pagedResponse($orders, OrderCardResource::class);
    }

    public function mostTips(): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), [
            'most_tips_amount' => $this->orderService->mostTippedAmount(),
        ]);
    }

    /**
     * Flag each row with whether its store sits in the zones the request asked about.
     *
     * Null when there is nothing to judge by -- no header, or a row with no store -- which the
     * resource reads as "do not narrow".
     */
    private function markStoreZoneReachable(mixed $orders, array $zoneIds): void
    {
        foreach ($orders as $order) {
            $storeZone = $order->store?->zone_id;

            $order->setAttribute(
                'store_zone_reachable',
                ($zoneIds === [] || $storeZone === null) ? null : in_array((int) $storeZone, $zoneIds, true)
            );
        }
    }

    private function listFilters(GuestOrderRequest $request): array
    {
        $type = $request->query('type', 'previous');

        return array_merge($this->orderOwner($request), [
            'module_id' => $this->headerModuleId($request),
            'type' => in_array($type, self::LIST_TYPES, true) ? $type : 'previous',
        ]);
    }

    private function moduleListResponse(mixed $paginator, array $counts, array $filters, string $resource): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), array_merge([
            'data' => $resource::collection($paginator),
            'pagination' => $this->paginateFormatter($paginator),
            'type' => $filters['type'],
            'module_id' => $filters['module_id'],
        ], $counts));
    }

    private function detailRows(mixed $order, ?string $saverDeliveryTime): array
    {
        // BOGO bundles and product bundles are each folded into one entry, the way the cart folds
        // them: a customer bought one thing and the receipt has to say so, even though the order
        // stores every member as its own row.
        //
        // BOGO first, then bundles over what it returns -- the same order the admin and vendor
        // order views use. A line is never both, and a folded BOGO entry carries no
        // bundle_group_id, so it passes through the second pass untouched.
        $details = app(BogoOrderService::class)->groupOrderDetails($order->details, (int) $order->store_id);
        $details = app(BundleOrderService::class)->groupOrderDetails($details);

        return OrderDetailResource::renderList($details, [
            'images' => $this->orderService->detailImages($order->details),
            'order' => $order,
            'saver_delivery_time' => $saverDeliveryTime,
        ]);
    }
}
