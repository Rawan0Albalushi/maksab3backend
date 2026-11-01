<?php

namespace App\Models;

use App\Traits\Model\HasStorageTrait;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\InvalidatesCacheTrait;
use App\Traits\Model\SlugTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An admin-run store-wide percentage discount that applies only inside a schedule.
 *
 * Scoped by MODULE, and by nothing else. The whole admin panel is module-scoped through the
 * header's switcher, so a happy hour belongs to whichever module the admin was in. There is no
 * zone: which zones it reaches follows from the stores that enrol in it, each of which carries
 * its own.
 *
 * Three schedule shapes share this table -- daily, weekly and custom -- and whichever is chosen,
 * the resolved windows are expanded into happy_hour_dates so the runtime asks one indexed
 * question instead of re-deriving a schedule for every item on a menu. The one exception is a
 * permanent weekly rule, which writes no dated rows and has to be matched against the definition.
 */
class HappyHour extends Model
{
    use HasFactory, HasStorageTrait, HasTranslationsTrait, SlugTrait, InvalidatesCacheTrait;

    // Same 'store' tag Discount busts: StoreDiscountResolver reads a happy hour and a store's
    // standing discount through the one resolver, and every cache that reads that resolver's
    // output (api.items_popular, api.service_popular, api.stores_top_offer, etc.) is tagged
    // 'store'. Without this, turning a window on/off (create/update/status/delete) leaves those
    // caches serving whatever was true before, for up to their full TTL.
    protected static array $cacheTags = ['store'];

    /** Default window length offered by the panel when a time range is not given. */
    public const DURATION_MINUTES = 60;

    public const DURATION_DAILY = 'daily';

    public const DURATION_WEEKLY = 'weekly';

    public const DURATION_CUSTOM = 'custom';

    protected $guarded = ['id'];

    protected $casts = [
        'module_id' => 'integer',
        'status' => 'integer',
        'admin_id' => 'integer',
        'discount' => 'float',
        'min_order_amount' => 'float',
        'is_permanent' => 'boolean',
        'weekly_days' => 'array',
        'custom_days' => 'array',
        'custom_times' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['cover_image_full_url', 'icon_full_url'];

    public function getTitleAttribute($value)
    {
        return $this->translatedAttribute('title', $value);
    }

    public function getShortDescriptionAttribute($value)
    {
        return $this->translatedAttribute('short_description', $value);
    }

    public function getCoverImageFullUrlAttribute(): ?string
    {
        return $this->storageFullUrl('happy_hour', 'cover_image', $this->cover_image, 'campaign');
    }

    public function getIconFullUrlAttribute(): ?string
    {
        return $this->storageFullUrl('happy_hour', 'icon', $this->icon, 'campaign');
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }


    public function dates()
    {
        return $this->hasMany(HappyHourDate::class);
    }

    public function enrollments()
    {
        return $this->hasMany(HappyHourStore::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'happy_hour_store')
            ->withPivot(['id', 'status', 'rejection_reason', 'rejected_by', 'requested_by', 'joined_at'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * Named forModule because a `module()` relation already exists here, and
     * a scope sharing a relation's name is unreachable: Eloquent resolves the relation method
     * first, so `HappyHour::module($id)` is a static call to a non-static method. Campaign carries
     * that collision today; there is no reason to copy it.
     */
    public function scopeForModule(Builder $query, $moduleId): Builder
    {
        return $query->where('module_id', $moduleId);
    }


    /** Both halves of the scope together -- the pair D2 requires, and the pair the index covers. */

    public function hasEnded(): bool
    {
        if ($this->is_permanent) {
            return false;
        }

        if ($this->end_date) {
            return $this->end_date->isBefore(today());
        }

        $lastDay = $this->dates()->max('applicable_date');

        return $lastDay !== null && Carbon::parse($lastDay)->isBefore(today());
    }

    /**
     * The listing twin of hasEnded(): happy hours that still have something left to run.
     *
     * The lists used to ask this as "end_date is null OR end_date >= today", where the null
     * branch existed for permanent weekly rules -- but a CUSTOM schedule leaves end_date null
     * too, so every custom happy hour passed for ever, however long ago its last day had been.
     * A store kept an expired one on its list with no way to be rid of it.
     *
     * Kept beside hasEnded() so the row rule and the query rule cannot drift, and phrased the
     * same way round: a row is dropped only when it positively has ended, so a schedule that has
     * not been expanded yet stays listed rather than vanishing.
     */
    public function scopeNotEnded(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('is_permanent', 1)
                // A null end date never satisfies this, which is what sends a custom schedule
                // to the branch below.
                ->orWhereDate('end_date', '>=', now()->toDateString())
                ->orWhere(function ($q) {
                    $q->whereNull('end_date')
                        ->where('is_permanent', 0)
                        ->where(function ($q) {
                            $q->whereDoesntHave('dates')
                                ->orWhereHas('dates', fn ($d) => $d->whereDate('applicable_date', '>=', now()->toDateString()));
                        });
                });
        });
    }

    /** The day this happy hour finishes, for the sentences that name it. Null while it has no end. */
    public function endsOn(): ?Carbon
    {
        if ($this->is_permanent) {
            return null;
        }

        if ($this->end_date) {
            return $this->end_date;
        }

        $lastDay = $this->dates()->max('applicable_date');

        return $lastDay ? Carbon::parse($lastDay) : null;
    }

    /**
     * Asked repeatedly of the same window -- once while a listing is primed, again for every card
     * that carries the payload -- and for a dated happy hour each ask is a query. The schedule
     * cannot move inside one request, so the first answer stands.
     *
     * Per instance, not static: a static memo outlives the row it was computed for.
     */
    private ?bool $runningNow = null;

    public function isRunningNow(): bool
    {
        return $this->runningNow ??= $this->resolveRunningNow();
    }

    /**
     * A permanent weekly rule writes no dated rows, so it is matched against the definition.
     * Everything else resolves from the expanded table.
     */
    private function resolveRunningNow(): bool
    {
        $now = now();
        $time = $now->format('H:i:s');

        if ($this->is_permanent && $this->duration_type === self::DURATION_WEEKLY) {
            return in_array($now->format('l'), $this->weekly_days ?? [], true)
                && $this->start_time <= $time
                && $this->end_time >= $time;
        }

        // A caller that eager-loaded `dates` (every listing path that prices more than one happy
        // hour does, precisely to avoid this) gets matched against the already-loaded collection
        // in memory -- querying $this->dates() here unconditionally defeated that eager load
        // entirely, since a relation *method* call always issues a fresh query regardless of what
        // was preloaded onto the $dates relation *property*. A caller that never eager-loaded it
        // still gets a correct answer via the query fallback.
        if ($this->relationLoaded('dates')) {
            $today = $now->toDateString();

            return $this->dates->contains(
                fn (HappyHourDate $date) => (int) $date->status === 1
                    && $date->applicable_date?->toDateString() === $today
                    && $date->start_time <= $time
                    && $date->end_time >= $time
            );
        }

        return $this->dates()
            ->where('status', 1)
            ->whereDate('applicable_date', $now->toDateString())
            ->whereTime('start_time', '<=', $time)
            ->whereTime('end_time', '>=', $time)
            ->exists();
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function (self $happyHour) {
            $happyHour->slug = $happyHour->generateSlug($happyHour->title);
            $happyHour->save();
        });

        static::saved(function (self $model) {
            foreach (['cover_image', 'icon'] as $key) {
                self::recordStorageDisk($model, $key, $key);
            }
        });
    }
}
