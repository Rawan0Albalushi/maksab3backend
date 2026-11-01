<?php

namespace App\Models;

use App\Services\System\ModuleService;
use App\CentralLogics\Helpers;
use Carbon\Carbon;
use App\Scopes\ZoneScope;
use App\Traits\Model\DemoMaskableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\Report\ReportFilterTrait;
use Modules\TaxModule\Entities\OrderTax;
use App\Traits\Model\HasStorageTrait;
use App\Traits\System\SidebarCountsTrait;

class Order extends Model
{
    use HasFactory, ReportFilterTrait, DemoMaskableTrait, HasStorageTrait, SidebarCountsTrait;

    protected $casts = [
        'order_amount' => 'float',
        'coupon_discount_amount' => 'float',
        'total_tax_amount' => 'float',
        'store_discount_amount' => 'float',
        'flash_admin_discount_amount' => 'float',
        'flash_store_discount_amount' => 'float',
        'delivery_address_id' => 'integer',
        'delivery_man_id' => 'integer',
        'delivery_charge' => 'float',
        'delivery_type_charge' => 'float',
        'surge_amount' => 'float',
        'additional_charge' => 'float',
        'original_delivery_charge' => 'float',
        'user_id' => 'integer',
        'zone_id' => 'integer',
        'scheduled' => 'integer',
        'store_id' => 'integer',
        'details_count' => 'integer',
        'module_id' => 'integer',
        'dm_vehicle_id' => 'integer',
        'processing_time' => 'integer',

        'delivery_duration' => 'integer',

        'eta_snapshot' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'extra_packaging_amount' => 'float',
        'receiver_details' => 'array',
        'dm_tips' => 'float',
        'distance' => 'float',
        'tax_percentage' => 'float',
        'prescription_order' => 'boolean',
        'cutlery' => 'boolean',
        'is_guest' => 'boolean',
        'ref_bonus_amount' => 'float',
        'bring_change_amount'=>'integer',
        'edited' => 'boolean',
        'adjusment' => 'float',
        'is_hidden' => 'boolean',
        'is_pos' => 'boolean',
        'is_self_delivery' => 'boolean',
        'parcel_category_id' => 'integer',
        'weight_id' => 'integer',
        'dimension_id' => 'integer',
    ];

    protected $appends = ['module_type','order_attachment_full_url','order_proof_full_url','distance_label'];

    public function getDistanceLabelAttribute(): ?string
    {
        $kilometres = (float) ($this->attributes['distance'] ?? 0);

        return $kilometres > 0 ? app(\App\Services\System\DistanceService::class)->format($kilometres) : null;
    }

    public ?array $eta = null;

    /**
     * Whether this order was self-delivery at placement time. Orders created before the
     * is_self_delivery column existed have it as null — for those, and only those, fall back
     * to the store's current setting rather than reporting a false "not self-delivery".
     */
    public function wasSelfDelivery(): bool
    {
        if (!is_null($this->is_self_delivery)) {
            return (bool) $this->is_self_delivery;
        }

        return (bool) ($this->store?->sub_self_delivery ?? false);
    }

    public function getOrderAttachmentFullUrlAttribute(){
        $images = [];
        $attachment = $this->order_attachment;

        $value = is_array($attachment)
            ? $attachment
            : ($attachment && is_string($attachment) && $this->isValidJson($attachment)
                ? json_decode($attachment, true)
                : []);

        if ($value) {
            foreach ($value as $item) {
                $item = is_array($item) ? $item : (is_object($item) && get_class($item) == 'stdClass' ? json_decode(json_encode($item), true) : ['img' => $item, 'storage' => 'public']);
                $images[] = Helpers::get_full_url('order', $item['img'], $item['storage'] ?? 'public');
            }
        }

        return $images;
    }

    public function getOrderProofFullUrlAttribute(){
        $images = [];
        $value = is_array($this->order_proof)
            ? $this->order_proof
            : ($this->order_proof && is_string($this->order_proof) && $this->isValidJson($this->order_proof)
                ? json_decode($this->order_proof, true)
                : []);
        if ($value){
            foreach ($value as $item){
                $item = is_array($item)?$item:(is_object($item) && get_class($item) == 'stdClass' ? json_decode(json_encode($item), true):['img' => $item, 'storage' => 'public']);
                $images[] = Helpers::get_full_url('order',$item['img'],$item['storage'] ?? 'public');
            }
        }

        return $images;
    }

    public function getDeliveryAddressAttribute($value)
    {
        if ($this->shouldMask()) {
            if (is_array($value)) {
                $address = $value;
            } elseif ($value instanceof \stdClass) {
                $address = (array) $value;
            } elseif (is_string($value)) {
                $address = json_decode($value, true);
            } else {
                $address = [];
            }

            if (!empty($address['contact_person_email'])) {
                $address['contact_person_email'] = $this->maskEmail($address['contact_person_email']);
            }

            if (!empty($address['contact_person_number'])) {
                $address['contact_person_number'] = $this->maskPhone($address['contact_person_number']);
            }
            return json_encode($address);
        }
        return $value;
    }

    public function getReceiverDetailsAttribute($value)
    {
        if (is_null($value)) {
            return null;
        }

        $address = is_array($value) ? $value : json_decode($value, true);
        if ($this->shouldMask()) {
            if (!empty($address['contact_person_email'])) {
                $address['contact_person_email'] = $this->maskEmail($address['contact_person_email']);
            }
            if (!empty($address['contact_person_number'])) {
                $address['contact_person_number'] = $this->maskPhone($address['contact_person_number']);
            }
        }

        return $address;
    }

    private function isValidJson($value)
    {
        json_decode($value);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    public function setDeliveryChargeAttribute($value)
    {
        $this->attributes['delivery_charge'] = round($value, 3);
    }

    public function cashback_history()
    {
        return $this->hasOne(CashBackHistory::class, 'order_id');
    }
    public function orderProDiscount()
    {
        return $this->hasOne(OrderProDiscount::class, 'order_id');
    }
    public function parcelCancellation()
    {
        return $this->hasOne(ParcelCancellation::class, 'order_id');
    }

    public function offline_payments()
    {
        return $this->belongsTo(OfflinePayments::class,'id','order_id');
    }

    public function details()
    {
        return $this->hasMany(OrderDetail::class);
    }
    public function reviews()
    {
        return $this->hasMany(Review::class , 'order_id');
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function delivery_man()
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class, 'user_id','id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_code', 'code');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function delivery_history()
    {
        return $this->hasMany(DeliveryHistory::class, 'order_id');
    }

    public function dm_last_location()
    {
        return $this->delivery_man?->last_location();
    }

    public function transaction()
    {
        return $this->hasOne(OrderTransaction::class);
    }

    public function parcel_category()
    {
        return $this->belongsTo(ParcelCategory::class);
    }

    /**
     * The weight band and the size class this parcel was charged by.
     *
     * Snake_case to match `parcel_category()` above — the three are read together everywhere and
     * a lone camelCase sibling would be the odd one out in every `with()` this codebase writes.
     *
     * Both are null on a non-parcel order, on a parcel placed before the tiers existed, and on one
     * whose (zone, module) does not price by that tier. Every reader has to handle that.
     */
    public function weight()
    {
        return $this->belongsTo(Weight::class);
    }

    public function dimension()
    {
        return $this->belongsTo(Dimension::class);
    }

    public function refund()
    {
        return $this->hasOne(Refund::class, 'order_id');
    }
    public function OrderReference()
    {
        return $this->hasOne(OrderReference::class, 'order_id');
    }

    public function getModuleTypeAttribute()
    {
        if (! $this->relationLoaded('module')) {
            $this->setRelation('module', app(ModuleService::class)->cachedById($this->module_id));
        }

        return $this->module ? $this->module->module_type : null;
    }

    public function getIsEditableAttribute(): bool
    {
        $campaignOrder = !empty(optional($this->details->first())->item_campaign_id);

        return in_array($this->order_status, ['pending'])
            && $this->order_type != 'pos'
            && isset($this->store)
            && !$campaignOrder
            && $this->prescription_order == 0
            && $this->payments->count() == 0
            && $this->ref_bonus_amount == 0
            && $this->flash_admin_discount_amount == 0
            && $this->payment_method == 'cash_on_delivery';
    }

    public function getCanReorderAttribute(): bool
    {
        $details = collect($this->details);
        $campaignOrder = $details->contains(fn ($detail) => !empty($detail->item_campaign_id));

        // A BOGO bundle or a Bundle package is assembled at checkout from an offer that may since
        // have been edited, ended or withdrawn -- there is no line here to drop back into a cart,
        // so an order holding one cannot be reordered.
        //
        // contains(), not every(): this asked whether EVERY line was part of a bundle, so an order
        // with a bundle alongside an ordinary item reordered happily and silently lost the bundle
        // half. And bundle_group_id was never looked at, so Bundle packages were never caught at
        // all -- only all-BOGO orders were.
        $hasPromotionGroup = $details->contains(
            fn ($detail) => !empty($detail->bogo_group_id) || !empty($detail->bundle_group_id)
        );

        return $this->order_type != 'parcel'
            && $this->prescription_order == 0
            && $this->flash_admin_discount_amount == 0
            && $this->flash_store_discount_amount == 0
            && !$campaignOrder
            && !$hasPromotionGroup;
    }

    public function getIsBogoAttribute(): bool
    {
        return collect($this->details)->contains(fn ($detail) => !empty($detail->bogo_group_id));
    }

    public function getIsHappyHourAttribute(): bool
    {
        return ! is_null($this->happy_hour_id);
    }

    /**
     * Whether the vendor's own standing store discount (as opposed to a happy hour, a bundle, or
     * a BOGO offer) is what produced store_discount_amount -- see
     * StoreDiscountResolver::bearerFor(): a happy hour always resolves to 'vendor', so this is
     * false whenever getIsHappyHourAttribute() is true, even though both share the same column.
     */
    public function getIsStoreDiscountAttribute(): bool
    {
        return $this->discount_on_product_by === 'admin' && $this->store_discount_amount > 0;
    }

    public function scopeAccepteByDeliveryman($query)
    {
        return $query->where('order_status', 'accepted');
    }

    public function scopePreparing($query)
    {
        return $query->whereIn('order_status', ['confirmed', 'processing', 'handover']);
    }

    public function scopeModule($query, $module_id)
    {
        return $query->where('module_id', $module_id);
    }

    public function scopeOngoing($query)
    {
        return $query->whereIn('order_status', ['accepted', 'confirmed', 'processing', 'handover', 'picked_up']);
    }

    public function scopeItemOnTheWay($query)
    {
        return $query->where('order_status', 'picked_up');
    }

    public function scopePending($query)
    {
        return $query->where('order_status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('order_status', 'failed')->whereDoesntHave('offline_payments');
    }

    public function scopeCanceled($query)
    {
        return $query->where('order_status', 'canceled');
    }

    public function scopeDelivered($query)
    {
        return $query->where('order_status', 'delivered');
    }

    public function scopeNotRefunded($query)
    {
        return $query->where(function ($query) {
            $query->whereNotIn('order_status', ['refunded']);
        });
    }

    public function scopeRefunded($query)
    {
        return $query->where('order_status', 'refunded');
    }
    public function scopeRefund_requested($query)
    {
        return $query->where('order_status', 'refund_requested');
    }

    public function scopeRefund_request_canceled($query)
    {
        return $query->where('order_status', 'refund_request_canceled');
    }

    public function scopeSearchingForDeliveryman($query)
    {
        return $query->whereNull('delivery_man_id')->whereIn('order_type', ['delivery', 'parcel'])->whereNotIn('order_status', ['delivered', 'failed', 'canceled', 'refund_requested', 'refund_request_canceled', 'refunded']);
    }

    public function scopeDelivery($query)
    {
        return $query->where('order_type', '=', 'delivery');
    }

    public function scopeScheduled($query)
    {
        return $query->whereRaw('created_at <> schedule_at')->where('scheduled', '1');
    }

    public function scopeOrderScheduledIn($query, $interval)
    {
        return $query->where(function ($query) use ($interval) {
            $query->whereRaw('created_at <> schedule_at')->where(function ($q) use ($interval) {
                $q->whereBetween('schedule_at', [Carbon::now()->toDateTimeString(), Carbon::now()->addMinutes($interval)->toDateTimeString()]);
            })->orWhere('schedule_at', '<', Carbon::now()->toDateTimeString());
        })->orWhereRaw('created_at = schedule_at');
    }

    public function scopeStoreOrder($query)
    {
        return $query->whereIn('order_type', ['take_away', 'delivery']);
    }

    public function scopeDmOrder($query)
    {
        return $query->whereIn('order_type', ['parcel', 'delivery']);
    }

    public function scopeParcelOrder($query)
    {
        return $query->where('order_type', 'parcel');
    }

    public function scopePos($query)
    {
        return $query->where('order_type', '=', 'pos');
    }

    public function scopeNotpos($query)
    {
        return $query->where('order_type', '<>', 'pos');
    }

    public function scopeNotHiddenForCustomer($query)
    {
        return $query->where($this->getTable() . '.is_hidden', 0);
    }

    public function scopeNotDigitalOrder($query)
    {
        return $query->where(function ($q){
            $q->whereNotIn('payment_method', ['digital_payment','offline_payment'])->orwhereNot('order_status' , 'pending');
        });
    }

    public function getCreatedAtAttribute($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }

    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);

        static::creating(function ($order) {
            // Parcel is admitted alongside delivery here too — EtaService::forOrder() and
            // snapshotFor() both resolve Parcel's own timings (see EtaService::effectiveTimings()),
            // so refusing it here would leave every Parcel order without a frozen snapshot while
            // Delivery orders get one, and an admin editing (or deactivating) the ETA configuration
            // later would move a Parcel order's estimate — or blank it — when it should hold still.
            if ($order->eta_snapshot !== null || ! in_array($order->order_type, ['delivery', 'parcel'], true)) {
                return;
            }

            try {
                $order->eta_snapshot = app(\App\Services\Order\EtaService::class)->snapshotFor($order);
            } catch (\Throwable $e) {
                info(['eta_snapshot_failed' => $e->getMessage()]);
            }
        });
    }
    protected static function boot()
    {
        parent::boot();
    }
    public function orderTaxes()
    {
        return $this->morphMany(OrderTax::class, 'order');
    }

    public function orderEditLogs()
    {
        return $this->hasMany(OrderEditLog::class, 'order_id')->latest();
    }
}
