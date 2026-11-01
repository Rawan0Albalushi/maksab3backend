<?php

namespace App\Models;

use App\Traits\Promotion\DescribesFrozenItemLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Service\Entities\Service;

class BundleItem extends Model
{
    use DescribesFrozenItemLine, HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'quantity' => 1,
        'unit_price' => 0,
    ];

    protected $casts = [
        'bundle_id' => 'integer',
        'item_id' => 'integer',
        'service_id' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'float',
        'item_price' => 'float',
        'variations' => 'array',
        'add_on_ids' => 'array',
        'add_on_qtys' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function targetId(): ?int
    {
        return $this->service_id ?? $this->item_id;
    }

    public function isService(): bool
    {
        return $this->service_id !== null;
    }

    protected static function booted(): void
    {
        static::saving(function (self $line) {
            if (($line->item_id !== null) === ($line->service_id !== null)) {
                throw new \InvalidArgumentException(
                    'A bundle line must reference exactly one of item_id or service_id.'
                );
            }
        });
    }
}
