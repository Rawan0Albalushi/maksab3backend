<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use App\CentralLogics\Helpers;
use App\Traits\Item\MissingAddonRelationsTrait;
use App\Traits\Report\ReportFilterTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\SlugTrait;
use App\Traits\Model\HasStorageTrait;
use App\Traits\Model\HasTranslationsTrait;
use Modules\Service\Entities\Service;

class StoreCategory extends Model
{
    use HasFactory, ReportFilterTrait, SlugTrait, MissingAddonRelationsTrait, HasStorageTrait, HasTranslationsTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['store_category'];

    protected $fillable = [
        'store_id',
        'module_id',
        'name',
        'slug',
        'image',
        'priority',
        'status',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'module_id' => 'integer',
        'priority' => 'integer',
        'status' => 'integer',
    ];

    protected $appends = ['image_full_url'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'store_category_id');
    }

    public function services(): HasMany
    {
        if (! service_addon_active()) {
            return $this->missingAddonHasMany();
        }

        return $this->hasMany(Service::class, 'store_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('category', 'image', $this->image);
    }

    public function getNameAttribute($value): string
    {
        return $this->translatedAttribute('name', $value);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($storeCategory) {
            $storeCategory->slug = $storeCategory->generateSlug($storeCategory->name);
            $storeCategory->save();
        });

        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });
    }

    protected static function booted()
    {

    }
}
