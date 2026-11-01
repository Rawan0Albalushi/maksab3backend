<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use App\CentralLogics\Helpers;
use App\Scopes\ZoneScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

class SmartBanner extends Model
{
    use HasFactory, HasTranslationsTrait, HasStorageTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['smart_banner'];

    protected $fillable = [
        'zone_id',
        'module_id',
        'active_days',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'position',
        'redirect_type',
        'redirect_target_id',
        'image',
        'status',
        'created_by',
    ];

    protected $casts = [
        'zone_id' => 'integer',
        'module_id' => 'integer',
        'status' => 'boolean',
        'redirect_target_id' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected $appends = [];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function getTitleAttribute(): ?string
    {
        return $this->translatedAttribute('title', null);
    }

    public function getSubtitleAttribute(): ?string
    {
        return $this->translatedAttribute('subtitle', null);
    }

    public function getImageFullUrlAttribute(): ?string
    {
        return $this->storageFullUrl('smart-banner', 'image', $this->image);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopePosition($query, $position)
    {
        return $query->where('position', $position);
    }

    public static function datesOverlap(
        string $activeDaysA,
        ?string $startDateA,
        ?string $endDateA,
        string $activeDaysB,
        ?string $startDateB,
        ?string $endDateB,
        ?string $referenceDate = null
    ): bool {
        $today = $referenceDate ? \Carbon\Carbon::parse($referenceDate) : \Carbon\Carbon::today();
        $infinity = (clone $today)->addYears(100);

        $aStart = $activeDaysA === 'everyday' ? $today : \Carbon\Carbon::parse($startDateA);
        $aEnd = $activeDaysA === 'everyday' ? $infinity : \Carbon\Carbon::parse($endDateA);
        $bStart = $activeDaysB === 'everyday' ? $today : \Carbon\Carbon::parse($startDateB);
        $bEnd = $activeDaysB === 'everyday' ? $infinity : \Carbon\Carbon::parse($endDateB);

        return !($aEnd->lt($bStart) || $aStart->gt($bEnd));
    }

    public static function timesOverlap(
        ?string $startTimeA,
        ?string $endTimeA,
        ?string $startTimeB,
        ?string $endTimeB
    ): bool {
        $aStart = $startTimeA ? \Carbon\Carbon::parse($startTimeA) : \Carbon\Carbon::parse('00:00:00');
        $aEnd = $endTimeA ? \Carbon\Carbon::parse($endTimeA) : \Carbon\Carbon::parse('23:59:59');
        $bStart = $startTimeB ? \Carbon\Carbon::parse($startTimeB) : \Carbon\Carbon::parse('00:00:00');
        $bEnd = $endTimeB ? \Carbon\Carbon::parse($endTimeB) : \Carbon\Carbon::parse('23:59:59');

        return !($aEnd->lt($bStart) || $aStart->gt($bEnd));
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new ZoneScope);
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {

            if ($model->image) {
                self::recordStorageDisk($model, 'image', 'image');
            }
        });



    }
}
