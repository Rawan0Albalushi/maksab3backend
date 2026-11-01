<?php

namespace App\Services\Payment;

use Modules\Rental\Entities\PartialPayment;
use App\Services\BaseService;

class PartialPaymentService extends BaseService
{
    public function findUnpaidForTrip(mixed $tripId): mixed
    {
        if (! addon_published_status('Rental')) {
            return null;
        }

        return PartialPayment::where('payment_status', 'unpaid')->where('trip_id', $tripId)->first();
    }
}
