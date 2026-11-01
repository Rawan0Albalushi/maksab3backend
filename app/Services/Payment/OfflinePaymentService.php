<?php

namespace App\Services\Payment;

use App\Models\OfflinePayments;
use App\Models\Order;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use App\Services\Payment\OfflinePaymentMethodService;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use App\Services\System\BusinessSettingService;

class OfflinePaymentService extends BaseService
{
    public function isEnabled(): bool
    {
        return app(BusinessSettingService::class)->value('offline_payment_status') != 0;
    }
    public function create(Order $order, array $data = []): OfflinePayments
    {
        $method = app(OfflinePaymentMethodService::class)->findActive($data['method_id'] ?? null);

        $payment = $this->forOrderQuery($order->id)->firstOrNew(['order_id' => $order->id]);
        $payment->payment_info = json_encode($this->buildPaymentInfo($method, $data['inputs'] ?? [], $data['method_id'] ?? null));
        $payment->customer_note = $data['customer_note'] ?? null;
        $payment->method_fields = json_encode($method?->method_fields);

        DB::transaction(function () use ($payment, $order) {
            $payment->save();

            $order->order_status = 'pending';
            $order->payment_method = 'offline_payment';
            $order->save();
        });

        $this->notifyAdmin($order);

        return $payment;
    }
    public function findForOrder(mixed $orderId): ?OfflinePayments
    {
        return $this->forOrderQuery($orderId)->first();
    }
    public function update(OfflinePayments $payment, array $data = []): OfflinePayments
    {
        $stored = json_decode($payment->payment_info, true);
        $method = app(OfflinePaymentMethodService::class)->find(data_get($stored, 'method_id'));

        $payment->customer_note = $data['customer_note'] ?? $payment->customer_note;
        $payment->payment_info = json_encode($this->buildPaymentInfo($method, $data['inputs'] ?? [], $method?->id));
        $payment->status = 'pending';
        $payment->save();

        return $payment;
    }
    public function notifyCustomer(Order $order): void
    {
        $token = $order->is_guest ? $order->guest?->fcm_token : $order->customer?->cm_firebase_token;

        if (! $token || ! SendNotification::channelEnabled('customer', 'customer_order_notification', 'push_notification_status')) {
            return;
        }

        $data = NotificationMessages::offlinePaymentInfoUpdated($order);

        SendNotification::pushToCustomer($order->user_id, $token, $data, isGuest: (bool) $order->is_guest);
    }
    private function forOrderQuery(mixed $orderId): mixed
    {
        return OfflinePayments::where('order_id', $orderId);
    }
    private function buildPaymentInfo(mixed $method, array $inputs, mixed $methodId): array
    {
        if (! $method) {
            return [];
        }

        $info = ['method_id' => $methodId, 'method_name' => $method->method_name];

        foreach (array_column($method->method_informations, 'customer_input') as $field) {
            if (array_key_exists($field, $inputs)) {
                $info[$field] = $inputs[$field];
            }
        }

        return $info;
    }
    private function notifyAdmin(Order $order): void
    {
        SendNotification::pushToTopic(
            NotificationMessages::newOrder($order, ['zone_id' => $order->zone_id]),
            'admin_message',
            'order_request',
            url('/').'/admin/order/list/all',
        );
    }
}
