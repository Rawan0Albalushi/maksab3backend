<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use DateTime;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

class Advertisement extends Model
{
    use HasFactory, HasTranslationsTrait, HasStorageTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['advertisement'];
    protected $guarded = ['id'];
    protected $casts = [
        'id' => 'integer',
        'is_paid' => 'integer',
        'is_rating_active' => 'integer',
        'is_review_active' => 'integer',
        'priority' => 'integer',
        'store_id' => 'integer',
        'created_by_id' => 'integer',
        'is_updated' => 'integer',
    ];
    public function created_by()
    {
        return $this->morphTo(__FUNCTION__, 'created_by_type', 'created_by_id');
    }
    protected $appends = ['active'];
    public function getCoverImageFullUrlAttribute()
    {
        return $this->storageFullUrl('advertisement', 'cover_image', $this->cover_image, 'ad_cover');
    }
    public function getProfileImageFullUrlAttribute()
    {
        return $this->storageFullUrl('advertisement', 'profile_image', $this->profile_image);
    }
    public function getVideoAttachmentFullUrlAttribute()
    {
        return $this->storageFullUrl('advertisement', 'video_attachment', $this->video_attachment);
    }
    public function getActiveAttribute(){

    $today = date('Y-m-d');

    $todayDate = new DateTime($today);
    $startDate = new DateTime($this->start_date);
    $endDate = new DateTime($this->end_date);
        if ($todayDate >= $startDate && $todayDate <= $endDate) {
            return  1;
        } elseif($todayDate < $startDate && $todayDate <= $endDate){
            return 2;
        }
        else {
            return  0;
        }
    }



    public function getTitleAttribute($value)
    {
        return $this->translatedAttribute('title', $value);
    }
    public function getDescriptionAttribute($value)
    {
        return $this->translatedAttribute('description', $value);
    }

    /*
     * Bare column comparisons, not whereDate(): date(end_date) >= ? cannot use an index, and
     * valid() is composed into every store and item list query. start_date/end_date are DATE
     * columns, so the comparison is equivalent.
     */
    public function scopeValid($query)
    {
        $today = date('Y-m-d');

        return $query->where('status', 'approved')->where('end_date', '>=', $today)->where('start_date', '<=', $today);
    }
    public function scopeApproved($query)
    {
        $today = date('Y-m-d');

        return $query->where('status', 'approved')->where('end_date', '>=', $today)->where('start_date', '>', $today);
    }
    public function scopeExpired($query)
    {
        return $query->where('status', 'approved')->where('end_date', '<', date('Y-m-d'));
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {

            self::recordStorageDisk($model, 'video_attachment', 'video_attachment');
            self::recordStorageDisk($model, 'cover_image', 'cover_image');
            self::recordStorageDisk($model, 'profile_image', 'profile_image');
        });

    }

}
