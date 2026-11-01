<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use App\Traits\Model\HasStorageTrait;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\SlugTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * An admin-created "buy N, get M" offer.
 *
 * The offer holds the rules; the items are held per store on the enrolment, because two stores
 * joining the same offer bundle different things at different prices. That is why there is no
 * items relation here -- ask an enrolment, not the offer.
 */
class BogoOffer extends Model
{
    use HasFactory, HasStorageTrait, HasTranslationsTrait, SlugTrait, InvalidatesCacheTrait;

    /**
     * The offer itself. Store listings cache a `bogo_offers` strip built from it, so editing or
     * retiring one has to drop those payloads.
     */
    protected static array $cacheTags = ['bogo'];

    /**
     * Whether an offer may be restricted to particular order types.
     *
     * Off by default and switched in one place rather than removed: the column, the stored
     * values, the panel field and every check stay put, they simply do not apply while this is
     * false. Flip it and BOGO restricts itself by order type again with nothing to rewrite.
     *
     * Note the set is narrower here than in the StackFood original, which also offered dine_in.
     * 6amMart has no dine-in concept -- PlaceOrderRequest validates
     * in:take_away,delivery,parcel -- and parcel carries no items to bundle. So an offer is
     * limited to delivery and take_away, and a stored dine_in would be a validation failure
     * rather than a silently ignored option.
     */
    public const ORDER_TYPES_ENABLED = false;

    public const ORDER_TYPES = ['delivery', 'take_away'];

    protected $guarded = ['id'];

    protected $casts = [
        'module_id' => 'integer',
        'status' => 'integer',
        'admin_id' => 'integer',
        'buy_qty' => 'integer',
        'get_qty' => 'integer',
        'usage_limit_total' => 'integer',
        'usage_limit_per_customer' => 'integer',
        'total_uses' => 'integer',
        'order_types' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['image_full_url'];

    public static function orderTypesEnabled(): bool
    {
        return self::ORDER_TYPES_ENABLED;
    }

    public function getTitleAttribute($value)
    {
        return $this->translatedAttribute('title', $value);
    }

    public function getDescriptionAttribute($value)
    {
        return $this->translatedAttribute('description', $value);
    }

    public function getImageFullUrlAttribute(): ?string
    {
        return $this->storageFullUrl('bogo_offer', 'image', $this->image, 'campaign');
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function enrollments()
    {
        return $this->hasMany(BogoOfferStore::class);
    }

    public function usages()
    {
        return $this->hasMany(BogoOfferUsage::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'bogo_offer_store')
            ->withPivot(['id', 'status', 'rejection_reason', 'rejected_by', 'requested_by', 'joined_at', 'bundle_price', 'combination_signature'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * Module is a required dimension here, unlike in the source.
     *
     * Named forModule rather than module because a `module()` relation already exists on this
     * model, and a scope of the same name is unreachable -- Eloquent resolves the relation method
     * first, so `BogoOffer::module($id)` is a static call to a non-static method rather than a
     * scope. Campaign carries that collision today; there is no reason to copy it.
     */
    public function scopeForModule(Builder $query, $moduleId): Builder
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query
            ->where(fn ($q) => $q->where('start_date', '<=', now())->orWhereNull('start_date'))
            ->where(fn ($q) => $q->where('end_date', '>=', now())->orWhereNull('end_date'));
    }

    /**
     * Once the whole-offer cap is reached the offer disappears entirely. The per-customer cap
     * behaves differently on purpose: it leaves the offer visible but refuses to add it, because
     * "you have used this" and "this is gone" are different things to tell someone.
     */
    public function scopeNotExhausted(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('usage_limit_total')
                ->orWhereColumn('total_uses', '<', 'usage_limit_total');
        });
    }

    /**
     * Buy/get quantities are editable only until the first store enrols. After that the enrolled
     * item sets have been built to total them and could no longer be reconciled.
     */
    public function isQuantityLocked(): bool
    {
        return $this->enrollments()->exists();
    }

    /**
     * Strand this offer's bundles in every cart holding them, whichever store they came from.
     *
     * The offer-wide counterpart to BogoOfferStore::strandCarts(), and it stopped DELETING for the
     * same reason: a customer whose only cart item was this bundle had their cart silently emptied
     * from somebody else's request, and Place Order then told them the cart was empty rather than
     * that the offer had gone. The rows stay, are shown unavailable, and are refused by name.
     *
     * Returns how many rows are stranded.
     */
    public function strandCarts(): int
    {
        return Cart::where('bogo_offer_id', $this->id)->count();
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function (self $offer) {
            $offer->slug = $offer->generateSlug($offer->title);
            $offer->save();
        });

        static::saved(function (self $model) {
            self::recordStorageDisk($model, 'image', 'image');
        });
    }
}
