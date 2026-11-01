<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\CentralLogics\Helpers;
use App\Traits\Model\SlugTrait;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

class PageSeoData extends Model
{
    use SlugTrait, HasTranslationsTrait, HasStorageTrait;

    protected $guarded = ['id'];

    protected $casts = [
        'status' => 'integer',
        'meta_data' => 'array',
    ];



    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('page_meta_data', 'image', $this->image);
    }







    protected static function booted()
    {
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });

        static::created(function ($data) {
            $data->slug = $data->generateSlug($data->name);
            $data->save();
        });


    }


}
