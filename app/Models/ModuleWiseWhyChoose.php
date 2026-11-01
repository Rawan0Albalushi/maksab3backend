<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

class ModuleWiseWhyChoose extends Model
{
    use HasFactory, HasTranslationsTrait, HasStorageTrait;

    protected $casts = [
        'status' => 'integer',
        'module_id' => 'integer',
    ];
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
    public function getTitleAttribute($value)
    {
        return $this->translatedAttribute('title', $value);
    }

    public function getShortDescriptionAttribute($value)
    {
        return $this->translatedAttribute('short_description', $value);
    }

    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('why_choose', 'image', $this->image);
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });

    }
}
