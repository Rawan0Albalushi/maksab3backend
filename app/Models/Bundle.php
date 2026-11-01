<?php

namespace App\Models;

use App\Services\Promotion\BundleCartService;
use App\Support\Promotion\BundleSettings;
use App\Traits\Model\InvalidatesCacheTrait;
use App\Traits\Model\HasStorageTrait;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\SlugTrait;
use App\Traits\Report\ReportFilterTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bundle extends Model
{
    use HasFactory, HasStorageTrait, HasTranslationsTrait, ReportFilterTrait, SlugTrait, SoftDeletes, InvalidatesCacheTrait;

    /**
     * A bundle changes what a store card advertises, so the store listings have to be rebuilt
     * with it -- their cached payloads carry `bundles` now (StorePromotionService).
     */
    protected static array $cacheTags = ['bundle'];

    public const MIN_ITEMS = 2;

    public const MAX_DISCOUNT_PERCENTAGE = 99;

    public const MAX_ITEMS = 50;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 1,
        'base_price' => 0,
        'discount_percentage' => 0,
        'discounted_price' => 0,
        'created_by' => 'admin',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'module_id' => 'integer',
        'base_price' => 'float',
        'discount_percentage' => 'float',
        'discounted_price' => 'float',
        'status' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['image_full_url'];

    protected static function booted(): void
    {
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });

        static::deleting(function (self $bundle) {
            app(BundleCartService::class)->strandCarts($bundle);

            if ($bundle->isForceDeleting()) {
                $bundle->translations()->delete();
                $bundle->storage()->delete();
            }
        });
    }

    public function getNameAttribute($value)
    {
        return $this->translatedAttribute('name', $value);
    }

    public function getDescriptionAttribute($value)
    {
        return $this->translatedAttribute('description', $value);
    }

    public function getImageFullUrlAttribute(): ?string
    {
        return $this->storageFullUrl('bundle', 'image', $this->image, 'campaign');
    }

    /**
     * Whether a member product's price has moved since this bundle was priced.
     *
     * A bundle's line prices are frozen on save -- that is what lets a placed order keep the total
     * the customer agreed to -- so a bundle goes on selling at its old figure after a product's
     * price changes, until an admin re-saves it. Nothing used to say so. This is what the list
     * badges, and it reads the frozen item_price rather than re-deriving the line, so the whole
     * page costs one eager-load instead of a query per member.
     *
     * A line with no frozen item_price predates the column and is treated as current: unknown is
     * not the same as stale, and badging every old bundle would make the badge worthless.
     */
    public function getHasStalePricingAttribute(): bool
    {
        if (! $this->relationLoaded('items')) {
            return false;
        }

        return $this->items->contains(function ($line) {
            if ($line->item_price === null || ! $line->relationLoaded('item') || ! $line->item) {
                return false;
            }

            return abs((float) $line->item->price - (float) $line->item_price) >= 0.01;
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function scopeForModule(Builder $query, $moduleId): Builder
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeForStore(Builder $query, $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->active()
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->running()->whereIn('module_id', BundleSettings::availableModuleIds());
    }

    /**
     * Bundles that still contain everything they were priced for.
     *
     * `bundle_items.item_id` is declared ON DELETE CASCADE, so deleting a product silently takes
     * its line out of every bundle holding it. The bundle survives -- with its ORIGINAL
     * base_price and discounted_price -- now containing less than it is charging for, and nothing
     * in the availability rule notices: that rule walks the lines it finds, and the missing one is
     * no longer there to be walked. An enrolment stripped to zero lines even reads as perfectly
     * available, because a loop over nothing reports nothing wrong.
     *
     * `base_price` is exactly SUM(bundle_items.unit_price) at save time (BundleService::sync()),
     * so a total that no longer adds up is proof a member has gone -- true for a bundle that lost
     * one line of three as much as for one stripped bare, which a count of lines cannot tell.
     * Compared with a tolerance rather than `=` because both sides are stored as floats.
     *
     * Applied to the LISTING paths only. The cart and checkout still resolve such a bundle, so a
     * customer already holding one is told what happened instead of being shown "not found".
     */
    public function scopeIntact(Builder $query): Builder
    {
        return $query->whereRaw(
            'ABS(COALESCE((SELECT SUM(bundle_items.unit_price) FROM bundle_items'
            .' WHERE bundle_items.bundle_id = bundles.id), 0) - bundles.base_price) < 0.01'
        );
    }

    public function hasStarted(): bool
    {
        return $this->start_date && $this->start_date->lte(now());
    }

    public function hasEnded(): bool
    {
        return $this->end_date && $this->end_date->lt(now());
    }

    public function isRunning(): bool
    {
        return (bool) $this->status && $this->hasStarted() && ! $this->hasEnded();
    }

    public function visibilityStatus(): string
    {
        if (! $this->status) {
            return 'not_visible';
        }

        if ($this->hasEnded()) {
            return 'ended';
        }

        return $this->hasStarted() ? 'running' : 'scheduled';
    }
}
