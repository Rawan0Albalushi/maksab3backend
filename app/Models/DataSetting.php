<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

class DataSetting extends Model
{
    use HasFactory, HasTranslationsTrait, HasStorageTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['data_setting'];
    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'integer',
    ];

    protected $fillable = [
        'key',
        'type',
        'value'
    ];

    public function getValueAttribute($value)
    {
        return $this->translatedAttribute($this->key, $value);
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'value');
        });

    }

}
