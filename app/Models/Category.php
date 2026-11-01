<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use App\CentralLogics\Helpers;
use App\Traits\Report\ReportFilterTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\SlugTrait;
use Modules\TaxModule\Entities\Taxable;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

/**
 * Class Category
 *
 * @property int $parent_id
 * @property int $position
 * @property int $priority
 * @property int $status
 * @property int $featured
 * @property int $module_id
 * @property int $products_count
 * @property int $childes_count
 * @property mixed $translations
 *
 * @package App\Models
 */
class Category extends Model
{
    use HasFactory, ReportFilterTrait, SlugTrait, HasTranslationsTrait, HasStorageTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['category', 'reference'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'parent_id',
        'position',
        'priority',
        'status',
        'featured',
        'module_id',
        'products_count',
        'childes_count',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'position' => 'integer',
        'priority' => 'integer',
        'status' => 'integer',
        'featured' => 'integer',
        'module_id' => 'integer',
        'products_count' => 'integer',
        'childes_count' => 'integer',
    ];
    protected $appends = ['image_full_url'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function scopeModule($query, $module_id)
    {
        return $query->where('module_id', $module_id);
    }

    public function scopeActive($query)
    {
        return $query->where('status', '=', 1);
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', '=', 1);
    }

    public function childes(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
    public static function defaultName($names, $langs): ?string
    {
        if (! is_array($names)) {
            return null;
        }
        $index = is_array($langs) ? array_search('default', $langs) : false;
        $name = $index !== false ? ($names[$index] ?? null) : ($names[0] ?? null);

        return ($name === null || trim($name) === '') ? null : $name;
    }

    public static function isDuplicateName(string $name, int $moduleId, int $parentId, ?int $ignoreId = null): bool
    {
        return static::withoutGlobalScopes()
            ->where('module_id', $moduleId)
            ->where('parent_id', $parentId)
            ->where('name', trim($name))
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('category', 'image', $this->image);
    }

    protected static function boot()
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

    public function getNameAttribute($value): string
    {
        return $this->translatedAttribute('name', $value);
    }

    protected static function booted(): Builder|null
    {

        return null;
    }
    public function taxVats()
    {
        return $this->morphMany(Taxable::class, 'taxable');
    }
}
