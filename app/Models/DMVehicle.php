<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Modules\RideShare\Entities\FareManagement\RideFare;
use Modules\RideShare\Entities\VehicleManagement\RiderVehicle;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

class DMVehicle extends Model
{
    use HasFactory, HasTranslationsTrait, HasStorageTrait;
    protected $guarded = ['id'];
    protected $casts = [
        'id' => 'integer',
        'status' => 'integer',
        'extra_charges' => 'float',
        'starting_coverage_area' => 'float',
        'maximum_coverage_area' => 'float',
        'max_weight' => 'float',
    ];

    protected $appends = ['image_full_url'];

    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('vehicle/category', 'image', $this->image, 'category');
    }

    public function delivery_man()
    {
        return $this->hasOne(DeliveryMan::class,'vehicle_id');
    }

    /**
     * Every deliveryman registered with this category.
     *
     * `delivery_man()` above is a hasOne and is kept for the callers that rely on it; a count
     * over a hasOne returns 1 at most, which is not what the "Total Delivery Man" column means.
     */
    public function deliveryMen()
    {
        return $this->hasMany(DeliveryMan::class, 'vehicle_id');
    }

    public function vehicles()
    {
        return $this->hasMany(RiderVehicle::class, 'category_id');
    }

    /**
     * "Dimension Connect" — the package size classes a vehicle in this category can carry.
     *
     * Empty is possible on rows that predate the field; the form requires at least one whenever
     * any dimension class exists to pick from.
     */
    public function dimensions()
    {
        return $this->belongsToMany(Dimension::class, 'd_m_vehicle_dimension', 'd_m_vehicle_id', 'dimension_id')
            ->withTimestamps();
    }

    public function tripFares()
    {
        return $this->hasMany(RideFare::class, 'vehicle_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function getTypeAttribute($value)
    {
        return $this->translatedAttribute('type', $value);
    }

    public function scopeRide($query)
    {
        return $query->withoutGlobalScope('delivery_only')->where('is_ride', 1);
    }

    protected static function booted()
    {
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });

        if(addon_published_status('RideShare')){
            static::addGlobalScope('delivery_only', function (Builder $builder) {
                $builder->where('is_delivery', 1);
            });
        }
    }
}
