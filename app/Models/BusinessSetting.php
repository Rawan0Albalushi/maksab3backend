<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use App\CentralLogics\Helpers;
use App\Services\System\BusinessSettingService;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasStorageTrait;
use App\Traits\Model\HasTranslationRelationTrait;

class BusinessSetting extends Model
{
    use HasStorageTrait, HasTranslationRelationTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['business_setting'];

    protected $guarded = ['id'];

    protected $fillable = [
        'key',
        'value'
    ];
    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            BusinessSettingService::forgetCache();
            BusinessSettingService::forgetModelMemo();

            self::recordStorageDisk($model, 'value');
        });

        static::deleted(function () {
            BusinessSettingService::forgetCache();
            BusinessSettingService::forgetModelMemo();
        });
    }

}
