<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One priced coverage row on a delivery rule — an area or a ZIP code and what it costs.
 *
 * Exactly one of `area_id` / `zip_code_id` is set, decided by the parent rule's pricing method.
 * No `module_id`: scope is inherited from the rule.
 *
 * @property int $id
 * @property int $delivery_rule_id
 * @property int|null $area_id
 * @property int|null $zip_code_id
 * @property float $charge
 */
class DeliveryRuleCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_rule_id',
        'area_id',
        'zip_code_id',
        'charge',
    ];

    protected $casts = [
        'id' => 'integer',
        'delivery_rule_id' => 'integer',
        'area_id' => 'integer',
        'zip_code_id' => 'integer',
        'charge' => 'float',
    ];

    public function deliveryRule(): BelongsTo
    {
        return $this->belongsTo(DeliveryRule::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function zipCode(): BelongsTo
    {
        return $this->belongsTo(ZipCode::class);
    }

    /**
     * What this row is called on the rule-detail screen — the area's name or the ZIP code,
     * whichever this row carries. Lives here rather than in the template because the template
     * would otherwise have to know which of the two columns is set.
     */
    public function label(): string
    {
        return $this->area?->name
            ?? $this->zipCode?->zip_code
            ?? translate('messages.N/A');
    }
}
