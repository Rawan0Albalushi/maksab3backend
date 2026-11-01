<?php

namespace App\Models;

use App\Traits\Report\ReportFilterTrait;
use Illuminate\Database\Eloquent\Model;

class DeliverymanReferralHistory extends Model
{
    use ReportFilterTrait;
    protected $casts = [
        'deliveryman_id' => 'integer',
        'referrer_id' => 'integer',
        'amount' => 'float',
    ];

    public function deliveryman()
    {
        return $this->belongsTo(Deliveryman::class);
    }
}
