<?php

namespace App\Models;

use App\Traits\Item\MissingAddonRelationsTrait;
use App\Traits\Report\ReportFilterTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Rental\Entities\Trips;
use Modules\RideShare\Entities\TripManagement\RideRequest;
use Modules\Service\Entities\ServiceBooking;

class Expense extends Model
{
    use MissingAddonRelationsTrait, HasFactory, ReportFilterTrait;
    protected $casts = [
        'id' => 'integer',
        'order_id' => 'integer',
        'store_id' => 'integer',
        'ride_id' => 'integer',
        'amount' => 'float',
        'created_at' => 'datetime',
    ];


    public function store()
    {
        return $this->belongsTo(Store::class,'store_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
    public function delivery_man()
    {
        return $this->belongsTo(DeliveryMan::class,'delivery_man_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function getCreatedAtAttribute($value)
    {
        return date('Y-m-d H:i:s',strtotime($value));
    }

    public function trip()
    {
        if (! addon_published_status('Rental')) {
            return $this->missingAddonRelation('trip_id');
        }

        return $this->belongsTo(Trips::class, 'trip_id');
    }

    public function ride()
    {
        if (! addon_published_status('RideShare')) {
            return $this->missingAddonRelation('ride_id');
        }

        return $this->belongsTo(RideRequest::class, 'ride_id');
    }

    public function serviceBooking()
    {
        if (! addon_published_status('Service')) {
            return $this->missingAddonRelation('service_booking_id');
        }

        return $this->belongsTo(ServiceBooking::class, 'service_booking_id');
    }

    public function scopeWithoutAddon($query)
    {
        return $query
            ->whereNull('ride_id')
            ->whereNull('trip_id')
            ->whereNull('service_booking_id');
    }

    // Earning figures already exclude a refunded order via Order::scopeNotRefunded() (joined
    // through order_transactions), but nothing excluded its expense rows -- a bundle_discount,
    // happy_hour_discount, bogo_discount or discount_on_product row written at delivery time
    // stayed counted in every "Total Expenses" figure forever, even after the order's own
    // revenue was correctly dropped. Trip/ride/service-booking expenses have no refunded concept
    // in this codebase (no status value for it on those models), so a row with no order_id is
    // left untouched -- this is a safe no-op for every addon-sourced expense query too.
    public function scopeNotRefunded($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('order_id')
                ->orWhereHas('order', fn ($order) => $order->where('order_status', '!=', 'refunded'));
        });
    }
}
