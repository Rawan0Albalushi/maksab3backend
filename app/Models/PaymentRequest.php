<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasUuidTrait;

class PaymentRequest extends Model
{
    use HasUuidTrait;
    use HasFactory;

    protected $table = 'payment_requests';
}
