<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

class ReactPromotionalBanner extends Model
{
    use HasFactory, HasTranslationsTrait, HasStorageTrait;
    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'integer',
        'status' => 'integer',
    ];

    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('promotional_banner', 'image', $this->image);
    }

    public function getTitleAttribute($value)
    {
        return $this->translatedAttribute('promotional_banner_title', $value);
    }
    public function getDescriptionAttribute($value)
    {
        return $this->translatedAttribute('promotional_banner_description', $value);
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });

    }
}
