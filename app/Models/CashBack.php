<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasTranslationsTrait;

class CashBack extends Model
{
    use HasFactory, HasTranslationsTrait;
    protected $guarded = ['id'];
    protected $casts = [
        'same_user_limit' => 'integer',
        'total_used' => 'integer',
        'cashback_amount' => 'float',
        'min_purchase' => 'float',
        'max_discount' => 'float',
        'status' => 'boolean',
    ];



    public function orders()
    {
        return $this->hasMany(Order::class,'cash_back_id')->where('is_guest',0);
    }
    public function getTitleAttribute($value): string
    {
        return $this->translatedAttribute('title', $value);
    }

       /**
     * @param $query
     * @return mixed
     */
    public function scopeActive($query): mixed
    {
        return $query->where('status', 1);
    }
    
    public function scopeRental($query): mixed
    {
        return $query->where('is_rental', 1);
    }

    public function scopeService($query): mixed
    {
        return $query->where('is_service', 1);
    }

    /**
     * @param $query
     * @return mixed
     */

    public function scopeRunning($query): mixed
    {
        return $query->where(function($q){
                $q->whereDate('end_date', '>=', date('Y-m-d'))->orWhereNull('end_date');
            })->where(function($q){
                $q->whereDate('start_date', '<=', date('Y-m-d'))->orWhereNull('start_date');
            });
    }

}
