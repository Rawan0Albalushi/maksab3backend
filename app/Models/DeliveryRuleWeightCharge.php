<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one weight band ADDS to a delivery rule's base charge.
 *
 * Additive, not a base — see the migration docblock. Nothing prices with this yet: the wizard
 * stores it, and the fee engine is wired in a later section.
 *
 * @property int $id
 * @property int $delivery_rule_id
 * @property int $weight_id
 * @property float $charge
 */
class DeliveryRuleWeightCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_rule_id',
        'weight_id',
        'charge',
    ];

    protected $casts = [
        'id' => 'integer',
        'delivery_rule_id' => 'integer',
        'weight_id' => 'integer',
        'charge' => 'float',
    ];

    public function deliveryRule(): BelongsTo
    {
        return $this->belongsTo(DeliveryRule::class);
    }

    public function weight(): BelongsTo
    {
        return $this->belongsTo(Weight::class);
    }
}
