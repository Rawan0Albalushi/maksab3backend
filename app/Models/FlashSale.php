<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasTranslationsTrait;

class FlashSale extends Model
{
    use HasFactory, HasTranslationsTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['flash_sale'];
    protected $casts = [
        'is_publish' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'module_id' => 'integer',
        'vendor_discount_percentage' => 'float',
        'admin_discount_percentage' => 'float',
    ];

    public function getTitleAttribute($value)
    {
        return $this->translatedAttribute('title', $value);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function products()
    {
        return $this->hasMany(FlashSaleItem::class,'flash_sale_id','id');
    }

    public function activeProducts()
    {
        return $this->hasMany(FlashSaleItem::class,'flash_sale_id','id')->where('available_stock' ,'>' ,0 )->where('status',1)->whereHas('item',function($query){
            $query->active();
        });
    }

    public function getStartTimeAttribute($value)
    {
        return $value?date('H:i',strtotime($value)):$value;
    }

    public function getEndTimeAttribute($value)
    {
        return $value?date('H:i',strtotime($value)):$value;
    }

    public function scopeActive($query)
    {
        return $query->where('is_publish', 1);
    }
    public function scopeModule($query, $module_id)
    {
        return $query->where('module_id', $module_id);
    }
    public function scopeRunning($query)
    {
        return $query->where('start_date','<=',date('Y-m-d H:i:s'))->where('end_date','>=',date('Y-m-d H:i:s'));
    }


}
