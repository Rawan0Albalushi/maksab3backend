<?php

namespace App\Models;

use App\Services\System\MeasurementUnitService;
use App\Traits\Model\HasTranslationsTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A named weight band — "0 - 2KG", "2 - 4KG" — that a parcel's package falls into.
 *
 * Global, not zone-scoped: the band classifies the package, and the zone-specific part is the
 * charge, which lives on the delivery rule. See the migration docblock.
 *
 * Kilograms always. These are SETUP values and are never converted through
 * `business_settings.distance_unit`, which governs distance only (port doc §3.2).
 *
 * @property int $id
 * @property string $name
 * @property float $from_weight
 * @property float $to_weight
 * @property bool $status
 */
class Weight extends Model
{
    use HasFactory, HasTranslationsTrait;

    protected $fillable = [
        'name',
        'from_weight',
        'to_weight',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'from_weight' => 'float',
        'to_weight' => 'float',
        'status' => 'boolean',
    ];

    /** Read by BaseResource's translation block and by the admin edit drawer. */
    public array $translatable = ['name'];

    public function getNameAttribute($value)
    {
        return $this->translatedAttribute('name', $value);
    }

    /**
     * "1 - 2 kg" — the band as every screen writes it.
     *
     * Lives here because four places need the identical string: the setup list, the delivery-rule
     * wizard, admin order details, and both order APIs. Three of them were formatting it
     * themselves, so a unit change or a separator change had to be made three times and the API
     * carried no formatted form at all.
     *
     * NOT in `$appends`. It resolves the unit service, and appending it would run that for every
     * row of a list that only wanted the numbers. Readers ask for it by name.
     */
    public function getBandLabelAttribute(): string
    {
        return $this->from_weight.' - '.$this->to_weight.' '.app(MeasurementUnitService::class)->weightUnitLabel();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * Bands read bottom-up in the design's table, so ascending is the only order that makes the
     * "ranges are matched top-to-bottom" note true.
     */
    public function scopeInBandOrder(Builder $query): Builder
    {
        return $query->orderBy('from_weight')->orderBy('to_weight');
    }
}
