<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\Traits\Model\HasStorageTrait;
use Illuminate\Database\Eloquent\Model;

class ItemSeoData extends Model
{
    use HasStorageTrait;

    protected $guarded = ['id'];
    protected $appends = ['image_full_url'];

    protected $casts = [
        'meta_data' => 'array',
    ];



    public function getImageFullUrlAttribute()
    {
        $value = $this->image;
        return $this->storageFullUrl('item_meta_data', 'image', $value);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
    protected static function booted(): void
    {
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });
    }

}
