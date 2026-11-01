<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

class ModuleWiseBanner extends Model
{
    use HasFactory, HasTranslationsTrait, HasStorageTrait;

    protected $casts = [
        'status' => 'integer',
        'module_id' => 'integer',
    ];

    protected $fillable = ['module_id', 'key', 'type', 'value'];

    public function scopeModule($query, $module_id)
    {
        return $query->where('module_id', $module_id);
    }

    public function module()
    {
        return $this->belongsTo(Module::class,'module_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function getValueAttribute($value)
    {
        return $this->translatedAttribute($this->key, $value);
    }

    public function getValueFullUrlAttribute()
    {
        return $this->storageFullUrl('promotional_banner', 'value', $this->value);
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'value', 'value');
        });

    }
}
