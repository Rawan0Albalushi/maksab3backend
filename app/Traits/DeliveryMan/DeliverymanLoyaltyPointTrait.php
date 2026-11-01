<?php

namespace App\Traits\DeliveryMan;

use App\CentralLogics\Helpers;
use App\Models\DeliverymanLoyaltyPointHistory;
use App\Services\DeliveryMan\DeliveryManService;
use App\Services\DeliveryMan\DeliveryManWalletService;
use App\Services\System\BusinessSettingService;
use App\Support\Notification\NotificationMessages;
use App\Support\Notification\SendNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait DeliverymanLoyaltyPointTrait
{
    public function recordDeliverymanLoyaltyPoint($deliveryManId, $amount, $transactionType, $pointConversionType = 'credit', $reference = null): array
    {
        $settings = app(BusinessSettingService::class)->valuesFor(['dm_loyality_point_status', 'dm_loyality_point_per_order', 'dm_loyality_point_conversion_rate']);
        if (data_get($settings, 'dm_loyality_point_status') != 1 || (data_get($settings, 'dm_loyality_point_per_order', 0) <= 0 && $pointConversionType == 'credit') || $amount <= 0) {
            return ['status_code' => 403, 'code' => 'loyalty_point', 'message' => translate('Loyalty point not enabled')];
        }

        $deliveryMan = app(DeliveryManService::class)->findBasic($deliveryManId);
        if (! $deliveryMan) {
            return ['status_code' => 403, 'code' => 'loyalty_point', 'message' => translate('No data found')];
        } elseif ($deliveryMan->earning != 1) {
            return ['status_code' => 403, 'code' => 'loyalty_point', 'message' => translate('Wallet not enabled')];
        }

        $loyalty_point_transaction = new DeliverymanLoyaltyPointHistory;
        $loyalty_point_transaction->delivery_man_id = $deliveryMan->id;
        $loyalty_point_transaction->transaction_id = Str::uuid();
        $loyalty_point_transaction->transaction_type = $transactionType;
        $loyalty_point_transaction->point_conversion_type = $pointConversionType;
        $point = $amount;
        if ($pointConversionType == 'credit') {
            $point = (int) ($settings['dm_loyality_point_per_order'] ?? 0);
            $deliveryMan->loyalty_point = $deliveryMan->loyalty_point + $point;
        } else {
            $deliveryMan->loyalty_point = $deliveryMan->loyalty_point - $point;
            if ($deliveryMan->loyalty_point < 0) {
                return ['status_code' => 403, 'code' => 'loyalty_point', 'message' => translate('messages.Insufficient point')];
            }

            $convertedAmount = $settings['dm_loyality_point_conversion_rate'] ?? 0;
            if ($convertedAmount == 0) {
                $convertedAmount = (int) ($amount / 1);
            } else {
                $convertedAmount = (float) ($amount / $convertedAmount);
            }

            $loyalty_point_transaction->converted_amount = $convertedAmount;

            $dmWallet = app(DeliveryManWalletService::class)->findOrNew($deliveryMan->id);
            $dmWallet->total_earning = $dmWallet->total_earning + $convertedAmount;
        }
        $loyalty_point_transaction->point = $point;
        $loyalty_point_transaction->reference = $reference;

        try {
            DB::beginTransaction();
            $deliveryMan->save();
            $loyalty_point_transaction->save();
            $loyalty_point_transaction->transaction_id = Helpers::generate_transaction_id($loyalty_point_transaction);
            $loyalty_point_transaction->save();

            if (isset($dmWallet)) {
                $dmWallet?->save();
            }
            DB::commit();

        } catch (\Exception $exception) {
            info(["line___{$exception->getLine()}", $exception->getMessage()]);
            DB::rollback();

            return ['status_code' => 403, 'code' => 'loyalty_point', 'message' => translate('messages.Something went wrong')];
        }

        try {
            $data = NotificationMessages::deliveryManLoyaltyPoint(
                $loyalty_point_transaction->id,
                $pointConversionType == 'credit'
                    ? translate('You have earned').' '.$point.' '.translate('Loyalty points')
                    : translate('You have converted').' '.$point.' '.translate('Loyalty points'),
            );
            if (SendNotification::channelEnabled('deliveryman', 'deliveryman_loyalty_point_transaction', 'push_notification_status') && $deliveryMan->fcm_token) {
                SendNotification::pushToDeliveryMan($deliveryMan->id, $deliveryMan->fcm_token, $data);

            }

        } catch (\Exception $exception) {
            info(["line___{$exception->getLine()}", $exception->getMessage()]);
        }

        return ['status_code' => 200, 'code' => 'loyalty_point', 'data' => $loyalty_point_transaction];
    }
}
