<?php

namespace App\Http\Controllers\Api\V1\Customer\Order;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Order\GuestOrderRequest;
use App\Http\Requests\Customer\Order\OfflinePaymentStoreRequest;
use App\Http\Requests\Customer\Order\OfflinePaymentUpdateRequest;
use App\Http\Requests\Customer\Order\OrderIdRequest;
use App\Mail\PlaceOrder;
use App\Services\Order\OrderService;
use App\Services\Payment\OfflinePaymentService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Support\Notification\SendNotification;

class OrderPaymentController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly OfflinePaymentService $offlinePaymentService
    ) {}

    public function update(GuestOrderRequest $request): JsonResponse
    {
        if (Helpers::get_business_settings('cash_on_delivery')['status'] == 0) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.Cash on delivery order not available at this time'), 'cod');
        }

        $order = $this->orderService->findForPayment(array_merge($this->orderOwner($request), ['order_id' => $request->input('order_id')]));

        if (! $order) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $this->notifyPlacement($this->orderService->switchToCashOnDelivery($order));

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function walletPayment(OrderIdRequest $request): JsonResponse
    {
        $order = $this->orderService->findForPayment(array_merge($this->orderOwner($request), ['order_id' => $request->input('order_id')]));

        if (! $order) {
            return $this->responseFormatter(config('response.default_404'));
        }

        if ($order->payment_status === 'paid' || $this->orderService->payFromWallet($order)) {
            return $this->responseFormatter(config('response.default_200'));
        }

        return $this->responseFormatter(config('response.bad_request_400'));
    }

    public function storeOffline(OfflinePaymentStoreRequest $request): JsonResponse
    {
        if (! $this->offlinePaymentService->isEnabled()) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.Offline payment for the order not available at this time'), 'offline_payment_status');
        }

        $order = $this->orderService->findForPayment(array_merge($this->orderOwner($request), ['order_id' => $request->input('order_id')]));

        if (! $order) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $this->offlinePaymentService->create($order, $request->payload());

        return $this->responseFormatter(config('response.default_store_201'));
    }

    public function updateOffline(OfflinePaymentUpdateRequest $request): JsonResponse
    {
        $order = $this->orderService->findForPayment(array_merge($this->orderOwner($request), ['order_id' => $request->input('order_id')]));
        $payment = $order ? $this->offlinePaymentService->findForOrder($order->id) : null;

        if (! $order || ! $payment) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $this->offlinePaymentService->update($payment, $request->payload());

        $request->notifiesCustomer()
            ? $this->offlinePaymentService->notifyCustomer($order)
            : SendNotification::sendOrderNotifications($order);

        return $this->responseFormatter(config('response.default_update_200'));
    }

    private function notifyPlacement(mixed $order): void
    {
        try {
            SendNotification::sendOrderNotifications($order);

            if (! config('mail.status') || ! SendNotification::mailTemplateEnabled('place_order_mail_status_user')) {
                return;
            }

            if (! config('mail.status') || ! SendNotification::channelEnabled('customer', 'customer_order_notification', 'mail_status')) {
                return;
            }

            $recipient = $order->is_guest
                ? (json_decode($order->delivery_address, true)['contact_person_email'] ?? null)
                : $order->customer?->getRawOriginal('email');

            if ($recipient) {
                SendNotification::mail($recipient, new PlaceOrder($order->id));
            }
        } catch (\Exception $exception) {
            Log::error('Order payment-method notification failed', ['order_id' => $order->id, 'message' => $exception->getMessage()]);
        }
    }
}
