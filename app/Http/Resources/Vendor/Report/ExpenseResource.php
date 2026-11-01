<?php

namespace App\Http\Resources\Vendor\Report;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ExpenseResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $expense = $this->resource;

        return array_merge(parent::toArray($request), $expense->attributesToArray(), [
            'customer_name' => $this->customerName(),
        ]);
    }

    private function customerName(): ?string
    {
        $order = $this->resource->relationLoaded('order') ? $this->resource->order : null;

        if (! $order) {
            return null;
        }

        if ($order->is_guest) {
            $address = Helpers::decodeJsonToArray($order->delivery_address) ?: [];

            return $address['contact_person_name'] ?? translate('messages.Guest user');
        }

        $customer = $order->relationLoaded('customer') ? $order->customer : null;

        return $customer ? trim($customer->f_name.' '.$customer->l_name) : null;
    }
}
