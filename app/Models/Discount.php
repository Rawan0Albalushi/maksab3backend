<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory, InvalidatesCacheTrait;

    // Covers any future write that goes through the Eloquent model. The one write path this
    // repo actually uses today -- VendorController::discountSetup()'s $store->discount()->
    // updateOrinsert(...) -- is a raw query-builder upsert that bypasses model events entirely,
    // so that call site busts the tag explicitly too; this is a backstop, not the whole fix.
    protected static array $cacheTags = ['store'];

    protected $fillable = [
        'start_date','end_date','start_time','end_time','min_purchase','max_discount','discount','discount_type','store_id',
    ];
    protected $casts = [
        'min_purchase' => 'float',
        'max_discount' => 'float',
        'discount' => 'float',
        'store_id'=>'integer',
        'created_at'=>'datetime',
        'updated_at'=>'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeValidate($query)
    {
        $query->whereDate('start_date','<=',date('Y-m-d'))->whereDate('end_date','>=',date('Y-m-d'))->whereTime('start_time','<=',date('H:i:s'))->whereTime('end_time','>=',date('H:i:s'));
    }
}
