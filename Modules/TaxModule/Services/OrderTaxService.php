<?php

namespace Modules\TaxModule\Services;

use App\Services\BaseService;
use Modules\TaxModule\Entities\OrderTax;

class OrderTaxService extends BaseService
{
    public function attachToOrder(array $orderTaxIds, mixed $orderId): void
    {
        OrderTax::whereIn('id', $orderTaxIds)->update(['order_id' => $orderId]);
    }

    public function deleteForOrderType(mixed $orderId, mixed $orderType): void
    {
        OrderTax::where('order_id', $orderId)->where('order_type', $orderType)->delete();
    }
}
