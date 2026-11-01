<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use Modules\TaxModule\Entities\Taxable;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

class ParcelCategory extends Model
{
    use HasFactory, HasTranslationsTrait, HasStorageTrait;

    protected $casts = [
        // The one charge a category carries, ADDED to whatever the delivery rule priced the
        // parcel at (owner decision 2026-09-03; parcel brief §1 model (a)).
        'charge' => 'float',
        // Deprecated with that decision. Kept so a rollback loses nothing; nothing reads them.
        'parcel_per_km_shipping_charge'=>'float',
        'parcel_minimum_shipping_charge'=>'float',
    ];

    protected $appends = ['image_full_url'];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function getNameAttribute($value)
    {
        return $this->translatedAttribute('name', $value);
    }

    public function getDescriptionAttribute($value)
    {
        return $this->translatedAttribute('description', $value);
    }

    public function scopeModule($query, $module_id)
    {
        return $query->where('module_id', $module_id);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('parcel_category', 'image', $this->image);
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });

    }
    public function taxVats()
    {
        return $this->morphMany(Taxable::class, 'taxable');
    }
}
