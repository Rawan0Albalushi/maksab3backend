<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

class FlutterSpecialCriteria extends Model
{
    use HasFactory, HasTranslationsTrait, HasStorageTrait;
    protected $appends = ['image_full_url'];

    public function getTitleAttribute($value)
    {
        return $this->translatedAttribute('title', $value);
    }
    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('special_criteria', 'image', $this->image);
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });

    }
}
