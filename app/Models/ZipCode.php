<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A ZIP/postal code inside a zone — the pick a customer makes when the zone's delivery rule
 * prices `zip_code_wise`.
 *
 * NOT globally unique: two zones legitimately share a code where their coverage overlaps, so
 * uniqueness is enforced per zone in the request rules, never by a database-wide index.
 *
 * No translations — a postal code is the same string in every language.
 *
 * @property int $id
 * @property int $zone_id
 * @property string $zip_code
 * @property bool $status
 */
class ZipCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'zip_code',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'zone_id' => 'integer',
        'status' => 'boolean',
    ];

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
        return $this->hasMany(DeliveryMan::class, 'zip_code_id');
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
