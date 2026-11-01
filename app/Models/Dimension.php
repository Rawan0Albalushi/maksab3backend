<?php

namespace App\Models;

use App\Services\System\MeasurementUnitService;
use App\Traits\Model\HasTranslationsTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A named package size class — "Small", "Medium" — with the maximum box it accepts.
 *
 * Global, not zone-scoped, for the same reason as Weight: the class describes the package and the
 * charge that varies by geography lives on the delivery rule.
 *
 * Inches always — SETUP values, never converted (port doc §3.2).
 *
 * @property int $id
 * @property string $name
 * @property float $max_length
 * @property float $max_width
 * @property float $max_height
 * @property bool $status
 */
class Dimension extends Model
{
    use HasFactory, HasTranslationsTrait;

    protected $fillable = [
        'name',
        'max_length',
        'max_width',
        'max_height',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'max_length' => 'float',
        'max_width' => 'float',
        'max_height' => 'float',
        'status' => 'boolean',
    ];

    public array $translatable = ['name'];

    public function getNameAttribute($value)
    {
        return $this->translatedAttribute('name', $value);
    }

    /**
     * "12 × 6 × 6 in" — the largest box this class accepts, as every screen writes it.
     *
     * Same reasoning as `Weight::band_label`, including why it is not appended.
     */
    public function getSizeLabelAttribute(): string
    {
        return $this->max_length.' × '.$this->max_width.' × '.$this->max_height
            .' '.app(MeasurementUnitService::class)->dimensionUnitLabel();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * Smallest box first, so the list reads Small → Extra Large as the design shows rather than
     * in creation order. Volume rather than any single side: a long flat box and a tall narrow
     * one are not ordered sensibly by length alone.
     */
    public function scopeInSizeOrder(Builder $query): Builder
    {
        return $query->orderByRaw('(max_length * max_width * max_height) asc');
    }
}
