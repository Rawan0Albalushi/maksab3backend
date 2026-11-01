<?php

namespace App\Traits\Parcel;

use App\Traits\Order\OrderRefundTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Parcel\ParcelCancellationService;
use App\Services\Admin\AdminWalletService;
use App\Services\Admin\AdminService;
use App\Services\DeliveryMan\DeliveryManWalletService;
use App\Support\Notification\SendNotification;
use App\Services\System\BusinessSettingService;

trait ParcelOrderCancellationTrait
{
    use ParcelFeesTrait;
    use OrderRefundTrait;

    public function validateParcelReturnRequest($request, $order)
    {
        $validationError = match (true) {
            ! $order => [
                'code' => 'order',
                'message' => translate('No data found'),
                'status_code' => 403,
            ],
            $order->order_type != 'parcel' => [
                'code' => 'parcel',
                'message' => translate('messages.Only parcel order can be returned'),
                'status_code' => 403,
            ],
            $order->order_status != 'canceled' => [
                'code' => 'parcel',
                'message' => translate('messages.You can return only canceled parcel orders'),
                'status_code' => 403,
            ],
            ! $order->parcelCancellation => [
                'code' => 'order',
                'message' => translate('messages.You have not requested for parcel return'),
                'status_code' => 403,
            ],
            $order->parcelCancellation->return_otp && $order->parcelCancellation->return_otp != $request->return_otp => [
                'code' => 'order',
                'message' => translate('Invalid return OTP'),
                'status_code' => 403,
            ],

            default => null,
        };

        if ($validationError) {
            return $validationError;
        }

        return null;
    }

    public function cancelParcelOrder($order, $cancelBy, $request)
    {
        if (in_array($order->order_status, ['canceled', 'delivered', 'returned'])) {
            return ['status_code' => 403, 'code' => 'complete_order', 'message' => translate('messages.You can not cancel a completed order')];
        }
        $code = 'success';
        $msg = translate('Parcel canceled successfully');
        $parcel_cancellation_basic_setup = app(BusinessSettingService::class)->value('parcel_cancellation_basic_setup');

        $return_fee_status = $parcel_cancellation_basic_setup['return_fee_status'] ?? 0;
        $return_fee = $parcel_cancellation_basic_setup['return_fee'] ?? 0;
        $do_not_charge_return_fee_on_deliveryman_cancel = $parcel_cancellation_basic_setup['do_not_charge_return_fee_on_deliveryman_cancel'] ?? 0;

        $orderOldStatus = $order->order_status;
        $order->order_status = 'canceled';
        $order->canceled = now();
        $order->canceled_by = $cancelBy;
        $order->save();

        $parcelCancellation = app(ParcelCancellationService::class)->findOrNewForOrder($order->id);
        $parcelCancellation->order_id = $order->id;
        $parcelCancellation->cancel_by = $cancelBy;
        $parcelCancellation->note = $request->note ?? null;
        $parcelCancellation->reason = json_encode($request->reason);

        if (in_array($orderOldStatus, ['picked_up'])) {
            $parcelCancellation->before_pickup = 0;
            $parcelCancellation->return_otp = random_int(1000, 9999);

            if ($return_fee_status == 1 && $return_fee > 0) {
                if ((in_array($cancelBy, ['deliveryman', 'admin_for_deliveryman']) && $do_not_charge_return_fee_on_deliveryman_cancel == 1)) {
                    $parcelCancellation->return_fee = 0;
                } else {
                    $chargeAmount = $order['delivery_charge'] + $order['total_tax_amount'] + $order['additional_charge'] - $order['coupon_discount_amount'] - $order['ref_bonus_amount'];
                    $parcelCancellation->return_fee = ($chargeAmount * $return_fee) / 100;
                }
            }

            $parcel_return_time_fee = app(BusinessSettingService::class)->value('parcel_return_time_fee');
            $parcel_return_time_fee_status = $parcel_return_time_fee['status'] ?? 0;
            $return_fee_for_dm = $parcel_return_time_fee['return_fee_for_dm'] ?? 0;

            if ($parcel_return_time_fee_status == 1 && $return_fee_for_dm > 0) {
                $parcelCancellation->dm_penalty_fee = $return_fee_for_dm;
                $parcelCancellation->return_date = now()->addDays((int) $parcel_return_time_fee['parcel_return_time'] ?? 1);
            }
        } else {
            if ($order->payment_status == 'paid' && $order->is_guest == 0) {
                if (app(BusinessSettingService::class)->value('wallet_status') == 1 && app(BusinessSettingService::class)->value('wallet_add_refund') == 1) {
                    $refunded = $this->refundBeforeDelivered($order);
                    if ($refunded) {
                        $this->notifyParcelRefund($order, true);
                    }
                } else {
                    $parcelCancellation->is_delivery_charge_refundable = 1;
                    $code = 'wallet_failed';
                    $msg = translate('messages.Parcel canceled successfully contact admin for refund');
                }
            } elseif ($order->payment_status == 'paid' && $order->is_guest == 1) {
                $code = 'wallet_failed';
                $msg = translate('messages.Parcel canceled successfully contact admin for refund');
                $parcelCancellation->is_delivery_charge_refundable = 1;
            }
        }

        $parcelCancellation->save();
        SendNotification::sendOrderNotifications($order);

        return ['status_code' => 200, 'code' => $code, 'message' => $msg];
    }

    public function settleDeliveryManParcelCancellation($order)
    {

        $return_fee = $order?->parcelCancellation?->return_fee ?? 0;
        DB::beginTransaction();

        $order->order_status = 'returned';
        $order->save();

        $order->parcelCancellation->return_fee_payment_status = 'paid';

        if ($order->payment_status == 'paid' && $order->is_guest == 0) {
            if (app(BusinessSettingService::class)->value('wallet_status') == 1 && app(BusinessSettingService::class)->value('wallet_add_refund') == 1) {
                $refunded = $this->refundBeforeDelivered($order);
                if ($refunded) {
                    $this->notifyParcelRefund($order, true);
                }
            } else {
                $order->parcelCancellation->is_delivery_charge_refundable = 1;
            }
        } elseif ($order->payment_status == 'paid' && $order->is_guest == 1) {
            $order->parcelCancellation->is_delivery_charge_refundable = 1;
        }

        $order->parcelCancellation->save();

        try {

            $adminWallet = app(AdminWalletService::class)->findOrNew(app(AdminService::class)->findSuperAdmin()->id);

            if ($order->delivery_man) {
                $dmWallet = app(DeliveryManWalletService::class)->findOrNew($order->delivery_man_id);
                if ($order->delivery_man->earning == 1) {
                    $dmWallet->total_earning = $dmWallet->total_earning + $return_fee;
                    $dmWallet->collected_cash = $dmWallet->collected_cash + $return_fee;
                    $this->logParcelReturnFee($order, $return_fee);
                } else {
                    $adminWallet->total_commission_earning = $adminWallet->total_commission_earning + $return_fee;
                }
            } else {
                $adminWallet->total_commission_earning = $adminWallet->total_commission_earning + $return_fee;
            }

            $adminWallet->save();

            if ($order->parcelCancellation->return_date) {
                $returnDate = Carbon::parse($order->parcelCancellation->return_date);
                if ($returnDate->isPast() && isset($dmWallet)) {
                    $dmWallet->collected_cash = $dmWallet->collected_cash + $order->parcelCancellation->dm_penalty_fee ?? 0;
                    $this->logParcelPenaltyFee($order, $order->parcelCancellation->dm_penalty_fee ?? 0);
                }
            }

            if (isset($dmWallet)) {
                $dmWallet->save();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return false;
        }

        return true;

    }
}
