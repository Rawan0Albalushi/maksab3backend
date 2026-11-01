<?php

namespace App\Models;

use App\Traits\Model\HasTranslationsTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named area inside a zone — the pick a customer makes when the zone's delivery rule prices
 * `area_wise`.
 *
 * Keyed on `zone_id` only. See the migration docblock for why this is not (zone, module).
 *
 * @property int $id
 * @property int $zone_id
 * @property string $name
 * @property string|null $display_name
 * @property bool $status
 */
class Area extends Model
{
    use HasFactory, HasTranslationsTrait;

    protected $fillable = [
        'zone_id',
        'name',
        'display_name',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'zone_id' => 'integer',
        'status' => 'boolean',
    ];

    /** Read by BaseResource's translation block and by the admin edit form. */
    public array $translatable = ['name', 'display_name'];

    public function getNameAttribute($value)
    {
        return $this->translatedAttribute('name', $value);
    }

    public function getDisplayNameAttribute($value)
    {
        return $this->translatedAttribute('display_name', $value);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function deliveryMen(): HasMany
    {
        return $this->hasMany(DeliveryMan::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeOfZone(Builder $query, mixed $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }
}
