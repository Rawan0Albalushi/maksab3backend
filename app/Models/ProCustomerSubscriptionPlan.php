<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasTranslationsTrait;

class ProCustomerSubscriptionPlan extends Model
{
    use HasFactory, HasTranslationsTrait;

    protected $guarded = ['id'];

    protected $casts = [
        'price'    => 'float',
        'duration' => 'integer',
        'status'   => 'integer',
    ];

    public function subscriptions()
    {
        return $this->hasMany(ProCustomerSubscription::class, 'plan_id');
    }

    public function activeSubscriptions()
    {
        return $this->hasMany(ProCustomerSubscription::class, 'plan_id')->where('status', 'active');
    }

    public function getPlanNameAttribute($value)
    {
        return $this->translatedAttribute('plan_name', $value);
    }

}
