<?php

namespace App\Models;

use App\Scopes\HostScope;
use App\Scopes\StoreScope;
use App\Scopes\ZoneScope;
use App\Traits\Model\DemoMaskableTrait;
use App\Traits\Payment\ProCustomerSubscriptionTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Modules\Rental\Entities\Trips;
use Modules\RideShare\Entities\PromotionManagement\AppliedCoupon;
use Modules\RideShare\Entities\TripManagement\RideRequest;
use App\Models\UserAccount;
use Modules\RideShare\Entities\UserManagement\UserLastLocation;
use App\Traits\Model\HasStorageTrait;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, DemoMaskableTrait, HasStorageTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'interest',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_phone_verified' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'order_count' => 'integer',
        'wallet_balance' => 'float',
        'loyalty_point' => 'integer',
        'ref_by' => 'integer',
        'pro_status' => 'boolean',
    ];
    protected $appends = ['image_full_url'];
    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('profile', 'image', $this->image);
    }

    public function getFullNameAttribute(): string
    {
        return $this->f_name . ' ' . $this->l_name;
    }

    public function scopeOfStatus($query, $status): void
    {
        $query->where('status', '=', $status);
    }

    public function orders()
    {
        return $this->hasMany(Order::class)->where('is_guest', 0);
    }
    public function trips()
    {
        return $this->hasMany(Trips::class)->where('is_guest', 0);
    }

    public function customerRides()
    {
        return $this->hasMany(RideRequest::class, 'customer_id');
    }

    public function lastLocations()
    {
        return $this->hasOne(UserLastLocation::class, 'user_id')->where('type', 'customer');
    }

    public function appliedCoupon()
    {
        return $this->hasOne(AppliedCoupon::class);
    }


    public function addresses(){
        return $this->hasMany(CustomerAddress::class);
    }

    public function userinfo()
    {
        return $this->hasOne(UserInfo::class,'user_id', 'id');
    }

    public function scopeZone($query, $zone_id=null){
        $query->when(is_numeric($zone_id), function ($q) use ($zone_id) {
            return $q->where('zone_id', $zone_id);
        });
    }

    protected static function booted()
    {
        static::addGlobalScope(new HostScope());

        static::retrieved(function () {
            static $checkedDate = null;

            $today = date('Y-m-d');
            if ($checkedDate === $today) {
                return;
            }
            $checkedDate = $today;

            $lastRun = DB::table('data_settings')
                ->where('key', 'subscription_expiry_last_run_at')
                ->where('type', 'notification_settings')
                ->value('value');

            if ($lastRun && Carbon::parse($lastRun)->isAfter(now()->subDay())) {
                return;
            }

            try {
                (new class { use ProCustomerSubscriptionTrait; })
                    ->expireDueSubscriptions(limit: 25, timeBudget: 3.0);
            } catch (\Throwable $e) {
                info('subscription_expiry_user_booted: ' . $e->getMessage());
            }
        });
    }
    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });

    }

    public function item_visit_log()
    {
        return $this->morphedByMany(Item::class ,'visitor_log' );
    }

    public function proCustomerSubscriptions()
    {
        return $this->hasMany(\App\Models\ProCustomerSubscription::class);
    }

    public function activeProCustomerSubscription()
    {
        return $this->hasOne(\App\Models\ProCustomerSubscription::class)->where('status', 'active')->latestOfMany();
    }

    public function proCustomerTransactions()
    {
        return $this->hasMany(\App\Models\ProCustomerTransaction::class);
    }
}
