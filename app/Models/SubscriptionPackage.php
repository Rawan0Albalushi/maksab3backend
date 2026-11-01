<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasTranslationsTrait;


class SubscriptionPackage extends Model
{
    use HasFactory, HasTranslationsTrait;

    protected $guarded = ['id'];
    protected $casts = [
        'price'=>'float',
        'validity'=>'integer',
        'chat'=>'integer',
        'review'=>'integer',
        'package_id'=>'integer',
        'status'=>'integer',
        'pos'=>'integer',
        'default'=>'integer',
        'mobile_app'=>'integer',
        'total_package_renewed'=>'integer',
        'self_delivery'=>'integer',
        'store_id'=>'integer',
        'is_trial'=>'integer',
        'max_order'=>'string',
        'max_product'=>'string',
    ];

    /**
     * @param $query
     * @param $status
     * @return void
     */
    public function scopeOfStatus($query, $status): void
    {
        $query->where('status', '=', $status);
    }

    public function transactions()
    {
        return $this->hasMany(SubscriptionTransaction::class, 'package_id');
    }
    public function currentSubscribers()
    {
        return $this->hasMany(StoreSubscription::class, 'package_id')->where('status' ,1);
    }
    public function Subscribers()
    {
        return $this->hasMany(StoreSubscription::class, 'package_id');
    }

    public function getPackageNameAttribute($value)
    {
        return $this->translatedAttribute('package_name', $value);
    }
    public function getTextAttribute($value)
    {
        return $this->translatedAttribute('text', $value);
    }

}
