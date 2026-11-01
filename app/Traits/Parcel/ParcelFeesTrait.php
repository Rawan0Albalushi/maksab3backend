<?php

namespace App\Traits\Parcel;

use App\CentralLogics\Helpers;
use App\Models\ParcelPenaltyFee;
use App\Models\ParcelReturnFees;

trait ParcelFeesTrait
{
    public function logParcelReturnFee($order, $returnFee)
    {
        return $this->createParcelFeeLog(ParcelReturnFees::class, $order, $returnFee);
    }

    public function logParcelPenaltyFee($order, $penaltyFee)
    {
        if (! $order->delivery_man_id) {
            return null;
        }

        return $this->createParcelFeeLog(ParcelPenaltyFee::class, $order, $penaltyFee);
    }

    private function createParcelFeeLog(string $model, $order, $amount)
    {
        $log = new $model;
        $log->order_id = $order->id;
        $log->delivery_man_id = $order->delivery_man_id ?? null;
        $log->amount = $amount;
        $log->save();

        $log->transaction_id = Helpers::generate_transaction_id($log);
        $log->save();

        return $log;
    }
}
