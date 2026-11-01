<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Scopes\ZoneScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\HasStorageTrait;
use App\Support\Notification\NotificationMessages;

/**
 * Class Notification
 *
 * @property int $id
 * @property string $title
 * @property string $description
 * @property string|null $image
 * @property bool $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $tergat
 * @property int|null $zone_id
 */
class Notification extends Model
{
    use HasStorageTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'description',
        'image',
        'status',
        'tergat',
        'zone_id',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * @return array
     */
    public function getDataAttribute(): array
    {
        return NotificationMessages::pushNotificationRecord($this->title, $this->description, $this->image);
    }

    /**
     * @return BelongsTo
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeActive($query): mixed
    {
        return $query->where('status', '=', 1);
    }

    /**
     * @param $value
     * @return string
     */
    public function getCreatedAtAttribute($value): string
    {
        return date('Y-m-d H:i:s',strtotime($value));
    }

    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('notification', 'image', $this->image);
    }

    /**
     * @return void
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new ZoneScope);
    }
    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });

    }
}
