<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one package size class ADDS to a delivery rule's base charge.
 *
 * Additive, not a base — see the migration docblock. Nothing prices with this yet.
 *
 * @property int $id
 * @property int $delivery_rule_id
 * @property int $dimension_id
 * @property float $charge
 */
class DeliveryRuleDimensionCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_rule_id',
        'dimension_id',
        'charge',
    ];

    protected $casts = [
        'id' => 'integer',
        'delivery_rule_id' => 'integer',
        'dimension_id' => 'integer',
        'charge' => 'float',
    ];

    public function deliveryRule(): BelongsTo
    {
        return $this->belongsTo(DeliveryRule::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class);
    }
}
