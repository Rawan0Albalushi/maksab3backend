<?php

namespace App\Http\Requests\Customer\Order;

class OfflinePaymentUpdateRequest extends OrderIdRequest
{
    public function payload(): array
    {
        return ['customer_note' => $this->input('customer_note'), 'inputs' => $this->all()];
    }

    public function notifiesCustomer(): bool
    {
        return (bool) $this->input('update_payment_info');
    }
}
