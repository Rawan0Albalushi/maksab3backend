<?php

namespace Modules\ReelsModule\Entities;

use App\Traits\Model\InvalidatesCacheTrait;
use App\Models\Store;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Modules\ReelsModule\Support\ReelModuleConfig;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

class Reel extends Model
{

    use HasFactory, HasTranslationsTrait, HasStorageTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['reel'];

    protected $guarded = ['id'];

    protected $casts = [
        'store_id' => 'integer',
        'module_id' => 'integer',
        'productable_id' => 'integer',
        'order_now_button' => 'boolean',
        'order_count' => 'integer',
        'total_sale_amount' => 'decimal:4',
        'is_always_visible' => 'boolean',
        'status' => 'boolean',
        'total_views' => 'integer',
        'total_likes' => 'integer',
        'total_store_visits' => 'integer',
        'created_by_id' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    protected $appends = ['reel_status_label'];

    public function created_by()
    {
        return $this->morphTo(__FUNCTION__, 'created_by_type', 'created_by_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function productable()
    {
        return $this->morphTo();
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(ReelEngagement::class, 'reel_id');
    }

    public function getDescriptionAttribute($value)
    {
        return $this->translatedAttribute('description', $value);
    }

    public function getThumbnailFullUrlAttribute(): ?string
    {
        if (! $this->thumbnail) {
            return null;
        }

        return $this->storageFullUrl('reels', 'thumbnail', $this->thumbnail);
    }

    public function getVideoFullUrlAttribute(): ?string
    {
        if (! $this->video) {
            return null;
        }

        return $this->storageFullUrl('reels', 'video', $this->video);
    }

    public function getReelStatusLabelAttribute(): string
    {
        return $this->status ? $this->window_state_label : 'deactivated';
    }

    public function getWindowStateLabelAttribute(): string
    {
        if ($this->is_always_visible) {
            return 'live';
        }

        $today = new DateTime(date('Y-m-d'));
        $startDate = $this->start_date ? new DateTime($this->start_date) : null;
        $endDate = $this->end_date ? new DateTime($this->end_date) : null;

        if ($startDate && $endDate && $today >= $startDate && $today <= $endDate) {
            return 'live';
        }

        if ($startDate && $today < $startDate) {
            return 'upcoming';
        }

        return 'expired';
    }

    public function scopeModuleWise($query)
    {
        if (!ReelModuleConfig::isMultiModule()) {
            return $query;
        }

        return $query->when(is_numeric(config('module.current_module_id')), function ($builder) {
            $builder->where('module_id', config('module.current_module_id'));
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('status', 1)
            ->where(function (Builder $builder) use ($now) {
                $builder->where('is_always_visible', 1)
                    ->orWhere(function (Builder $dateQuery) use ($now) {
                        $dateQuery->where('is_always_visible', 0)
                            ->whereNotNull('start_date')
                            ->whereNotNull('end_date')
                            ->where('start_date', '<=', $now)
                            ->where('end_date', '>=', $now);
                    });
            });
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            self::recordStorageDisk($model, 'thumbnail', 'thumbnail');

            self::recordStorageDisk($model, 'video', 'video');
        });
    }

}
