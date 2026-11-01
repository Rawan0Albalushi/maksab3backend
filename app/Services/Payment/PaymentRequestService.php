<?php

namespace App\Services\Payment;

use App\Models\PaymentRequest;
use App\Services\BaseService;

class PaymentRequestService extends BaseService
{
    public function find(mixed $id): ?PaymentRequest
    {
        return PaymentRequest::find($id);
    }
}
