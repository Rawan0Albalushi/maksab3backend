<?php

namespace App\Http\Resources\DeliveryMan\Report;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class EarningTransactionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'order_id' => $this->resource['order_id'],
            'raw_order_id' => $this->resource['raw_order_id'],
            'order_date' => $this->resource['order_date'],
            'delivery_man' => $this->resource['delivery_man'],
            'delivery_charge' => $this->resource['delivery_charge'],
            'tips' => $this->resource['tips'],
            'commission_paid' => $this->resource['commission_paid'],
            'net_profit' => $this->resource['net_profit'],
            'date' => $this->resource['date'],
        ];
    }
}
