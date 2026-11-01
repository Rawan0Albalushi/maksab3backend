<?php

namespace App\Traits\Payment;

use App\Models\AccountTransaction;
use App\Services\System\UserNotificationService;
use App\Services\Vendor\VendorService;
use App\Services\Store\StoreService;
use App\Services\DeliveryMan\DeliveryManService;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use App\Services\System\BusinessSettingService;
use Illuminate\Support\Facades\Log;

trait CashCollectionTrait
{
    public function recordCashCollection($oldCollectedCash, $fromType, $fromId, $amount, $reference)
    {
        $account_transaction = new AccountTransaction;
        $account_transaction->from_type = $fromType;
        $account_transaction->from_id = $fromId;
        $account_transaction->created_by = $fromType;
        $account_transaction->method = 'cash_collection';
        $account_transaction->ref = $reference;
        $account_transaction->amount = $amount ?? 0;
        $account_transaction->current_balance = $oldCollectedCash ?? 0;
        $account_transaction->type = 'cash_in';
        $account_transaction->save();

        if ($fromType == 'store') {
            $vendor = app(VendorService::class)->find($fromId);
            $Payable_Balance = $vendor?->wallet?->collected_cash > 0 ? 1 : 0;
            $cash_in_hand_overflow = app(BusinessSettingService::class)->value('cash_in_hand_overflow_store', false);
            $cash_in_hand_overflow_store_amount = app(BusinessSettingService::class)->value('cash_in_hand_overflow_store_amount', false);

            if ($Payable_Balance == 1 && $cash_in_hand_overflow && $vendor?->wallet?->balance < 0 && $cash_in_hand_overflow_store_amount <= abs($vendor?->wallet?->collected_cash)) {
                $rest = app(StoreService::class)->findForVendor($vendor->id);
                $rest->status = 0;
                $rest->save();
            }
        } elseif ($fromType == 'deliveryman') {
            $cash_in_hand_overflow = app(BusinessSettingService::class)->value('cash_in_hand_overflow_delivery_man', false);
            $cash_in_hand_overflow_delivery_man = app(BusinessSettingService::class)->value('dm_max_cash_in_hand', false);

            $dm = app(DeliveryManService::class)->findBasic($fromId);
            $wallet_balance = $dm?->wallet?->total_earning - ($dm?->wallet?->total_withdrawn + $dm?->wallet?->pending_withdraw + $dm?->wallet?->collected_cash);
            $over_flow_balance = $dm?->wallet?->collected_cash;
            $Payable_Balance = $over_flow_balance > 0 ? 1 : 0;
            if ($Payable_Balance == 1 && $cash_in_hand_overflow && $wallet_balance < 0 && $cash_in_hand_overflow_delivery_man < abs($over_flow_balance)) {
                $dm->status = 0;
                try {
                    if (SendNotification::channelEnabled('deliveryman', 'deliveryman_account_block', 'push_notification_status') && isset($dm->fcm_token)) {
                        $data = NotificationMessages::suspendedForCashLimit();
                        SendNotification::sendToDevice($dm->fcm_token, $data);

                        app(UserNotificationService::class)->recordForDeliveryMan($dm->id, $data);
                    }
                } catch (\Throwable $th) {
                    Log::warning('payment.cash_collection_trait.record_cash_collection_failed', [
                        'error' => $th->getMessage(),
                        'file' => $th->getFile().':'.$th->getLine(),
                    ]);
                }
                $dm->auth_token = null;
                $dm->save();
            }

        }

        return true;
    }
}
