<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use App\Traits\Model\SlugTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Model\HasTranslationsTrait;

/**
 * Class CommonCondition
 *
 * @property int $id
 * @property string $name
 * @property string|null $slug
 * @property bool $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CommonCondition extends Model
{
    use HasFactory, SlugTrait, HasTranslationsTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['common_condition'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'status' => 'integer',
    ];

    /**
     * @return MorphMany
     */
    /**
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(PharmacyItemDetails::class,'common_condition_id','id')->whereNotNull('item_id');
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
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();
        static::created(function ($category) {
            $category->slug = $category->generateSlug($category->name);
            $category->save();
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

    /**
     * @return void
     */
}
