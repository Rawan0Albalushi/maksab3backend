<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use App\CentralLogics\Helpers;
use App\Scopes\ZoneScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

/**
 * Class Banner
 *
 * @property int $id
 * @property string $title
 * @property string $type
 * @property string|null $image
 * @property bool $status
 * @property string $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $zone_id
 * @property int $module_id
 * @property bool $featured
 * @property string|null $default_link
 * @property string $created_by
 */
class Banner extends Model
{
    use HasFactory, HasTranslationsTrait, HasStorageTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['banner'];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'type',
        'image',
        'status',
        'data',
        'zone_id',
        'module_id',
        'featured',
        'default_link',
        'created_by',
        'time_period',
        'start_date',
        'end_date',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'data' => 'integer',
        'status' => 'boolean',
        'zone_id' => 'integer',
        'module_id' => 'integer',
        'featured' => 'boolean',
        'time_period' => 'string',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected $appends = [];

    /**
     * @return MorphMany
     */
    public function store()
    {
        return $this->belongsTo(Store::class, 'data');
    }

    /**
     * @param $value
     * @return mixed
     */
    public function getTitleAttribute($value): mixed
    {
        return $this->translatedAttribute('title', $value);
    }

    /**
     * @return BelongsTo
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return BelongsTo
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @param $query
     * @param $module_id
     * @return mixed
     */
    public function scopeModule($query, $module_id): mixed
    {
        return $query->where('module_id', $module_id);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeActive($query): mixed
    {
        return $query->where('status', '=', 1);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeFeatured($query): mixed
    {
        return $query->where('featured', '=', 1);
    }

    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('banner', 'image', $this->image);
    }

    /**
     * @return void
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new ZoneScope);
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {

            self::recordStorageDisk($model, 'image', 'image');
        });


    }
}
