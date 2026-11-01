<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\Models\User;
use App\Traits\Model\HasStorageTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class DMReview extends Model
{
    use HasStorageTrait;

    protected $casts = [
        'delivery_man_id' => 'integer',
        'order_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function getAttachmentFullUrlAttribute(): array
    {
        return $this->attachmentUrls($this->attachment);
    }

    /**
     * How many files are attached to this review. Lives here so listing views can
     * show the chip without decoding the raw `attachment` JSON themselves.
     */
    public function getAttachmentCountAttribute(): int
    {
        return count(Helpers::decodeJsonToArray($this->attachment));
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            self::recordStorageDisk($model, 'attachment', 'attachment');
        });
    }

    public function customer()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function delivery_man()
    {
        return $this->belongsTo(DeliveryMan::class,'delivery_man_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status',1);
    }
}
