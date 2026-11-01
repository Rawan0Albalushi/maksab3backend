<?php

namespace App\Models;

use App\Scopes\ZoneScope;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\SlugTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\TaxModule\Entities\Taxable;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;
use App\Traits\Model\InvalidatesCacheTrait;

class ItemCampaign extends Model
{
    use HasFactory, SlugTrait, HasTranslationsTrait, HasStorageTrait, InvalidatesCacheTrait;

    /**
     * The only promotion model that busted nothing.
     *
     * A running item campaign is embedded in store cards by
     * StoreDataTrait::offersByStore(), whose payloads are cached for 5-10 minutes under the
     * `store`/`item` tags, and the basic-campaign list is cached under `campaign`. Deleting or
     * editing one reached neither, so a campaign that was gone from `item_campaigns` kept
     * being advertised on every cached store card until the TTL ran out.
     */
    protected static array $cacheTags = ['campaign', 'item'];

    protected $casts = [
        'tax' => 'float',
        'price' => 'float',
        'discount' => 'float',
        'status' => 'integer',
        'store_id' => 'integer',
        'category_id' => 'integer',
        'module_id' => 'integer',
        'maximum_cart_quantity' => 'integer',
        'veg' => 'integer',
        'stock'=>'integer',
        'created_at'=>'datetime',
        'updated_at'=>'datetime',
        'start_date'=>'datetime',
        'end_date'=>'datetime',
        'start_time'=>'datetime',
        'end_time'=>'datetime',
    ];

    public function carts()
    {
    return $this->morphMany(Cart::class, 'item');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class,'unit_id');
    }
    public function allergies()
    {
        return $this->belongsToMany(Allergy::class);
    }
    public function generic()
    {
        return $this->belongsToMany(GenericName::class,'item_campaign_generic_names');
    }
    public function nutritions()
    {
        return $this->belongsToMany(Nutrition::class);
    }

    public function getTitleAttribute($value)
    {
        return $this->translatedAttribute('title', $value);
    }

    public function getDescriptionAttribute($value)
    {
        return $this->translatedAttribute('description', $value);
    }

    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('campaign', 'image', $this->image);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function orderdetails()
    {
        return $this->hasMany(OrderDetail::class)->latest();
    }

    public function scopeModule($query, $module_id)
    {
        return $query->where('module_id', $module_id);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1)
        ->whereHas('store', function($query) {
            $query->where('status', 1)
                    ->where(function($query) {
                        $query->where('store_business_model', 'commission')
                                ->orWhereHas('store_sub', function($query) {
                                    $query->where(function($query) {
                                        $query->where('max_order', 'unlimited')->orWhere('max_order', '>', 0);
                                    });
                                });
                    });
            });
    }

    public function scopeRunning($query)
    {
        return $query->whereDate('end_date', '>=', date('Y-m-d'));
    }

    protected static function booted()
    {
        static::addGlobalScope(new ZoneScope);
    }
    protected static function boot()
    {
        parent::boot();
        static::created(function ($itemcampaign) {
            $itemcampaign->slug = $itemcampaign->generateSlug($itemcampaign->title);
            $itemcampaign->save();
        });
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });
    }         public function taxVats()
    {
        return $this->morphMany(Taxable::class, 'taxable');
    }
}
