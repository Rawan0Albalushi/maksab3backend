<?php

namespace App\Traits\Order;

use App\Services\System\UserNotificationService;
use App\Traits\Payment\CustomerTransactionsTrait;
use App\Services\Admin\AdminWalletService;
use App\Services\Admin\AdminService;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use App\Services\System\BusinessSettingService;
use Illuminate\Support\Facades\Log;

trait OrderRefundTrait
{
    use CustomerTransactionsTrait;

    public function refundBeforeDelivered($order)
    {
        $adminWallet = app(AdminWalletService::class)->findOrNew(app(AdminService::class)->findSuperAdmin()->id);
        if ($order->payment_method == 'cash_on_delivery') {
            return false;
        }
        if (($order->payment_status == 'paid')) {

            $adminWallet->digital_received = $adminWallet->digital_received - $order->order_amount;
            $adminWallet->save();
            if (app(BusinessSettingService::class)->value('wallet_add_refund', false) == 1 && $order->is_guest == 0) {
                $this->recordWalletTransaction($order->user_id, $order->order_amount, 'order_refund', $order->id);
            }
        } elseif (($order->payment_status == 'partially_paid')) {

            $adminWallet->digital_received = $adminWallet->digital_received - $order->partially_paid_amount;
            $adminWallet->save();
            if (app(BusinessSettingService::class)->value('wallet_add_refund', false) == 1 && $order->is_guest == 0) {
                $this->recordWalletTransaction($order->user_id, $order->partially_paid_amount, 'order_refund', $order->id);
            }
        }

        return true;
    }

    public function notifyParcelRefund($order, $wallet = true)
    {
        try {
            if (SendNotification::channelEnabled('customer', 'customer_refund_request_approval', 'push_notification_status') && $order?->customer?->cm_firebase_token) {
                $data = NotificationMessages::parcelDeliveryChargeRefunded($order, $wallet);
                SendNotification::sendToDevice($order?->customer?->cm_firebase_token, $data);
                app(UserNotificationService::class)->record($order->user_id, $data);
            }
        } catch (\Throwable $th) {
            Log::error('order.order_refund_trait.notify_parcel_refund_failed', [
                'error' => $th->getMessage(),
                'file' => $th->getFile().':'.$th->getLine(),
            ]);
        }

        return true;
    }
}
