<?php

namespace App\Models;

use App\Scopes\StoreScope;
use App\Scopes\ZoneScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Modules\TaxModule\Entities\Taxable;
use App\Traits\Model\HasTranslationsTrait;

/**
 * Class AddOn
 *
 * @property int $id
 * @property string $name
 * @property float $price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $store_id
 * @property bool $status
 * @property int $addon_category_id
 */
class AddOn extends Model
{
    use HasTranslationsTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'price',
        'store_id',
        'addon_category_id',
        'status',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'addon_category_id' => 'integer',
        'price' => 'float',
        'store_id' => 'integer',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * @return MorphMany
     */
    /**
     * @param $value
     * @return mixed
     */
    public function getNameAttribute($value)
    {
        return $this->translatedAttribute('name', $value);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeActive($query): mixed
    {
        return $query->where('status', 1);
    }

    /**
     * @return BelongsTo
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return void
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new StoreScope);
        static::addGlobalScope(new ZoneScope);
    }
        public function taxVats()
    {
        return $this->morphMany(Taxable::class, 'taxable');
    }

}
