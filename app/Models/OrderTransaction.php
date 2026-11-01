<?php

namespace App\Models;

use App\Scopes\ZoneScope;
use App\Traits\Report\ReportFilterTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderTransaction extends Model
{
    use HasFactory, ReportFilterTrait;

    protected $casts = [
        'pro_discount' => 'float',
        'pro_delivery_discount' => 'float',
        'delivery_type_charge' => 'float',
        'surge_amount' => 'float',
    ];

    protected $fillable = array('delivery_man_id');

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function store()
    {
        return $this->belongsTo(Store::class,'vendor_id','vendor_id');
    }

    public function delivery_man()
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }

    public function scopeModule($query, $module_id)
    {
        return $query->where('module_id', $module_id);
    }

    public function scopeNotRefunded($query)
    {
        return $query->where(function($query){
            $query->whereNotIn('status', ['refunded_with_delivery_charge', 'refunded_without_delivery_charge'])->orWhereNull('status');
        });
    }
    public function scopeRefunded($query)
    {
        return $query->where(function($query){
            $query->whereIn('status', ['refunded_with_delivery_charge', 'refunded_without_delivery_charge']);
        });
    }
    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);
    }
}
