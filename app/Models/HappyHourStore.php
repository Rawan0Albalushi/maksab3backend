<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One store's enrolment in one happy hour. Renamed from the source's HappyHourRestaurant.
 *
 * The same approval shape as BogoOfferStore, deliberately: both features run the same request /
 * approve / deny / withdraw conversation, and the shared vocabulary is what lets one set of
 * notification rows and one enrolment trait serve them both.
 *
 * What it does not have is items. Joining a happy hour is accepting a rate on everything the
 * store already sells, so there is nothing to freeze and nothing to amend -- which is why a
 * rejected happy hour enrolment cannot be resubmitted the way a rejected BOGO one can, and why
 * the vendor panel needs a join drawer for BOGO and a single click here.
 */
class HappyHourStore extends Model
{
    use HasFactory, InvalidatesCacheTrait;

    // See HappyHour::$cacheTags -- an enrolment's accept/reject/withdraw is what actually turns
    // the store-wide rate on or off for a given store, not just the window's own status.
    protected static array $cacheTags = ['store'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'happy_hour_store';

    protected $fillable = [
        'happy_hour_id',
        'store_id',
        'status',
        'rejection_reason',
        'rejected_by',
        'requested_by',
        'checked',
        'joined_at',
    ];

    protected $casts = [
        'happy_hour_id' => 'integer',
        'store_id' => 'integer',
        'checked' => 'boolean',
        'joined_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function happyHour()
    {
        return $this->belongsTo(HappyHour::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
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
}
