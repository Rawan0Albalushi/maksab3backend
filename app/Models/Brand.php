<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use App\Traits\Report\ReportFilterTrait;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\SlugTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

/**
 * Class Brand
 *
 * @property int $id
 * @property string $name
 * @property string|null $slug
 * @property bool $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Brand extends Model
{
    use HasFactory, SlugTrait, ReportFilterTrait, HasTranslationsTrait, HasStorageTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['brand'];

    /**
     * @var string[]
     */
    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'image',
        'status',
    ];

    protected $appends = ['image_full_url'];

    /**
     * @return MorphMany
     */
    /**
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(EcommerceItemDetails::class,'brand_id','id');
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeActive($query): mixed
    {
        return $query->where('status', '=', 1);
    }

    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('brand', 'image', $this->image);
    }

    /**
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();
        static::created(function ($category) {
            $category->slug = $category->generateSlug($category->name);
            $category->save();
        });
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });
    }

    /**
     * @param $name
     * @return string
     */
    /**
     * @param $value
     * @return mixed
     */
    public function getNameAttribute($value): mixed
    {
        return $this->translatedAttribute('name', $value);
    }
}
