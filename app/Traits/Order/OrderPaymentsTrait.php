<?php

namespace App\Traits\Order;

use App\Models\OrderPayment;
use App\Services\Order\OrderPaymentService;
use App\Services\Payment\PartialPaymentService;

trait OrderPaymentsTrait
{
    public function createOrderPayment($orderId, $amount, $paymentStatus, $paymentMethod)
    {
        $payment = new OrderPayment;
        $payment->order_id = $orderId;
        $payment->amount = $amount;
        $payment->payment_status = $paymentStatus;
        $payment->payment_method = $paymentMethod;
        if ($payment->save()) {
            return true;
        }

        return false;
    }

    public function markUnpaidOrderPaymentPaid($orderId, $paymentMethod)
    {
        $payment = app(OrderPaymentService::class)->findUnpaidForOrder($orderId);

        return $this->settleUnpaidPayment($payment, $paymentMethod);
    }

    public function markUnpaidTripPaymentPaid($tripId, $paymentMethod)
    {
        $payment = app(PartialPaymentService::class)->findUnpaidForTrip($tripId);
        $this->settleUnpaidPayment($payment, $paymentMethod, true);

        return true;
    }

    private function settleUnpaidPayment($payment, $paymentMethod, bool $preserveWallet = false)
    {
        if (! $payment) {
            return true;
        }

        $payment->payment_status = 'paid';
        if ($paymentMethod != 'partial_payment') {
            $payment->payment_method = $preserveWallet && $payment->payment_method == 'wallet' ? 'wallet' : $paymentMethod;
        }

        if ($payment->save()) {
            return true;
        }

        return false;
    }
}
