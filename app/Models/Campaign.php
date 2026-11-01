<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\SlugTrait;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;
use App\Support\Cache\ApiCache;

class Campaign extends Model
{
    use HasFactory, SlugTrait, HasTranslationsTrait, HasStorageTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['campaign'];

    protected $casts = [
        'status' => 'integer',
        'admin_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    protected $appends = [];

    public function getTitleAttribute($value)
    {
        return $this->translatedAttribute('title', $value);
    }

    public function getDescriptionAttribute($value)
    {
        return $this->translatedAttribute('description', $value);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function getStartTimeAttribute($value)
    {
        return $value?date('H:i',strtotime($value)):$value;
    }

    public function getEndTimeAttribute($value)
    {
        return $value?date('H:i',strtotime($value)):$value;
    }
    public function stores()
    {
        return $this->belongsToMany(Store::class)->withPivot('campaign_status','updated_at','created_at');
    }

    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('campaign', 'image', $this->image);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeModule($query, $module_id)
    {
        return $query->where('module_id', $module_id);
    }

    public function scopeRunning($query)
    {
        return $query->where(function($q){
                $q->whereDate('end_date', '>=', date('Y-m-d'))->orWhereNull('end_date');
            })->where(function($q){
                $q->whereDate('start_date', '<=', date('Y-m-d'))->orWhereNull('start_date');
            })->where(function($q){
                $q->whereTime('start_time', '<=', date('H:i:s'))->orWhereNull('start_time');
            })->where(function($q){
                $q->whereTime('end_time', '>=', date('H:i:s'))->orWhereNull('end_time');
            });
    }

    protected static function boot()
    {
        parent::boot();
        static::created(function ($campaign) {
            $campaign->slug = $campaign->generateSlug($campaign->title);
            $campaign->save();
        });
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');

        });
    }
}
