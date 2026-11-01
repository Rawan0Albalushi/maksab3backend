<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One store's enrolment in one BOGO offer.
 *
 * Renamed from the source's BogoOfferRestaurant. It looks like a pivot but carries the whole
 * approval conversation, and the bundle items hang off it rather than off the offer -- so this,
 * not BogoOffer, is what you ask "what does this store actually sell under that offer".
 *
 * The id of this row is a third id in a feature that already has two, and the three are never
 * interchangeable: bogo_offer_id is the offer, bogo_group_id is one bundle instance in one cart,
 * and this is the store's terms. Reading one as another is a bug the source shipped once.
 */
class BogoOfferStore extends Model
{
    use HasFactory, InvalidatesCacheTrait;

    /**
     * A store JOINING or leaving an offer changes that store's card, not the offer's own row, so
     * the enrolment busts the same tag the offer does.
     */
    protected static array $cacheTags = ['bogo'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'bogo_offer_store';

    protected $fillable = [
        'bogo_offer_id',
        'store_id',
        'status',
        'rejection_reason',
        'rejected_by',
        'requested_by',
        'checked',
        'joined_at',
        'bundle_price',
        'combination_signature',
    ];

    protected $casts = [
        'bogo_offer_id' => 'integer',
        'store_id' => 'integer',
        'checked' => 'boolean',
        'bundle_price' => 'float',
        'joined_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function bogoOffer()
    {
        return $this->belongsTo(BogoOffer::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function items()
    {
        return $this->hasMany(BogoOfferItem::class);
    }

    public function buyItems()
    {
        return $this->hasMany(BogoOfferItem::class)->where('type', 'buy');
    }

    public function getItems()
    {
        return $this->hasMany(BogoOfferItem::class)->where('type', 'get');
    }

    /**
     * The frozen selection in the shape the item picker works in, so Edit opens on what the store
     * already agreed to rather than on an empty drawer.
     *
     * Only the choices travel -- which item, how many, which variation and add-ons. Everything the
     * picker renders and prices with is read from the live menu when the drawer loads: the
     * snapshot records what was chosen, not the item it was chosen against.
     *
     * @return array{buy: array<int, array<string, mixed>>, get: array<int, array<string, mixed>>}
     */
    public function pickerPayload(): array
    {
        return $this->items
            ->groupBy('type')
            ->map(fn ($lines) => $lines->map(fn (BogoOfferItem $line) => [
                'item_id' => (int) $line->item_id,
                'quantity' => (int) $line->quantity,
                'selected_variations' => $line->variations ?? [],
                'add_on_ids' => $line->add_on_ids ?? [],
                'add_on_qtys' => $line->add_on_qtys ?? [],
            ])->values()->all())
            // Both sides are always present, so the picker never has to test for a missing key on
            // an enrolment that happens to have no get lines.
            ->union(['buy' => [], 'get' => []])
            ->all();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Take this store's bundles for this offer out of every cart holding them.
     *
     * Called by whichever action ends the arrangement -- the store leaving, the admin removing
     * it, a denial -- so a customer's cart is right immediately rather than at their next read.
     * A bundle nobody is enrolled to serve cannot be honoured, and left behind it shows as a
     * zero-priced row that fails at checkout.
     */
    /**
     * Strand the carts holding this bundle, rather than emptying them.
     *
     * This used to DELETE the rows, and that is what put "You cannot place empty orders" in front
     * of a customer whose only cart item was a bundle the store had just left: the rows vanished
     * during the ADMIN's request, so by the time the customer pressed Place Order there was
     * nothing left to explain why. An order refused for a reason the customer cannot see is worse
     * than one refused for a reason they can.
     *
     * Leaving them costs nothing and everything downstream already expects it:
     *   - bogoBundleIsGone() answers true the moment the enrolment stops being approved
     *   - BogoGroupPresenter renders the line with is_available:false and a reason
     *   - bogoUnavailableReason() refuses it at checkout, by name
     *
     * The rows can never be ORDERED in this state -- that is what the refusal is for -- so the
     * only thing that survives is the customer's ability to see what happened and clear it.
     * purgeDeadBogoBundles() exists to sweep them if a scheduled clean-up is ever wanted.
     *
     * Returns the number of rows left stranded, which is what every call site logs or ignores.
     */
    public function strandCarts(): int
    {
        return Cart::where('bogo_offer_id', $this->bogo_offer_id)
            ->where('store_id', $this->store_id)
            ->count();
    }
}
