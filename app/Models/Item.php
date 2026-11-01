<?php

namespace App\Models;

use App\Services\Promotion\BundleService;
use App\Traits\Model\InvalidatesCacheTrait;
use App\Scopes\ZoneScope;
use App\Scopes\StoreScope;
use App\Traits\Model\SlugTrait;
use App\Traits\Model\HasProductVideoPreviewTrait;
use App\Traits\Item\ItemFilterTrait;
use App\Traits\Report\ReportFilterTrait;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\TaxModule\Entities\Taxable;
use App\Traits\Model\HasTranslationsTrait;
use App\Traits\Model\HasStorageTrait;

class Item extends Model
{
    use HasFactory, ReportFilterTrait, HasProductVideoPreviewTrait, SlugTrait, ItemFilterTrait, HasTranslationsTrait, HasStorageTrait, InvalidatesCacheTrait;

    protected static array $cacheTags = ['item'];
    protected $guarded = ['id'];
    protected $with = ['storeCategory'];
    protected $casts = [
        'tax' => 'float',
        'price' => 'float',
        'status' => 'integer',
        'discount' => 'float',
        'avg_rating' => 'float',
        'set_menu' => 'integer',
        'category_id' => 'integer',
        'store_id' => 'integer',
        'store_category_id' => 'integer',
        'reviews_count' => 'integer',
        'recommended' => 'integer',
        'maximum_cart_quantity' => 'integer',
        'organic' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'veg' => 'integer',
        'images' => 'array',
        'module_id' => 'integer',
        'is_approved' => 'integer',
        'stock' => 'integer',
        "min_price" => 'float',
        "max_price" => 'float',
        'order_count' => 'integer',
        'rating_count' => 'integer',
        'unit_id' => 'integer',
        'is_halal' => 'integer',
    ];

    protected $appends = ['unit_type', 'video_size', 'video_preview_type', 'video_embed_url', 'video_preview_url', 'video_thumbnail_url', 'video_preview_modal_type', 'video_preview_modal_url', 'has_video_preview', 'has_video_source'];

    public function scopeRecommended($query)
    {
        return $query->where('recommended', 1);
    }

    public function scopeStoreCategory($query, $storeCategoryId)
    {
        return $query->when(is_numeric($storeCategoryId), function ($q) use ($storeCategoryId) {
            $q->where('store_category_id', $storeCategoryId);
        });
    }

    public function carts()
    {
        return $this->morphMany(Cart::class, 'item');
    }

    public function temp_product()
    {
        return $this->hasOne(TempProduct::class, 'item_id')->with('translations');
    }

    public function scopeDiscounted($query)
    {

        $nowDate = now()->format('Y-m-d');
        $nowTime = now()->format('H:i');
        // flash_sales keeps start_date/end_date as DATETIME, unlike discounts which uses
        // DATE, so "starts on or before today" is everything before tomorrow midnight.
        $tomorrow = now()->addDay()->format('Y-m-d');

        return $query->where(function ($query) use ($nowDate, $nowTime, $tomorrow) {
            $query->where('discount', '>', 0)
                // Bare comparisons, not whereDate()/whereTime(): a column wrapped in a
                // function cannot use an index. discounts dates are DATE, times are TIME.
                ->orWhereHas('store.discount', function ($q) use ($nowDate, $nowTime) {
                    $q->where('start_date', '<=', $nowDate)
                        ->where('end_date', '>=', $nowDate)
                        ->where('start_time', '<=', $nowTime)
                        ->where('end_time', '>=', $nowTime);
                })
                ->orWhereHas('flashSaleItems.flashSale', function ($q) use ($nowDate, $tomorrow) {
                    $q->where('is_publish', 1)
                        ->where('start_date', '<', $tomorrow)
                        ->where('end_date', '>=', $nowDate);
                });
        });
    }

    public function scopeModule($query, $module_id)
    {
        return $query->where('module_id', $module_id);
    }


    public function scopeActive($query , $zone_ids = null ,$module_id = null)
    {
        $module_id = $module_id && is_numeric($module_id) ? $module_id : null;
        $current_module_data = config('module.current_module_data');
        $zone_module_id = $module_id ?? ($current_module_data['id'] ?? null);

        return $query
        ->where('status', 1)->where('is_approved', 1)
            ->when($module_id, function ($query) use ($module_id) {
                $query->where('module_id', $module_id);
            })
            ->whereHas('store', function ($query) use ($zone_ids, $zone_module_id) {
                $query->where('status', 1)
                    ->where(function ($query) {
                        $query->where('store_business_model', 'commission')
                            ->orWhereHas('store_sub', function ($query) {
                                $query->where('max_order', 'unlimited')->orWhere('max_order', '>', 0);
                            });
                    })
                    ->when($zone_ids && is_array($zone_ids), function ($query) use ($zone_ids, $zone_module_id) {
                        $query->whereIn('zone_id', $zone_ids)
                            ->whereHas('zone.modules', function ($query) use ($zone_module_id) {
                                $query->when($zone_module_id, function ($query) use ($zone_module_id) {
                                    $query->where('modules.id', $zone_module_id);
                                });
                            });
                    });
            })
            ->whereHas('module', function ($query) {
                $query->where('status', 1);
            })
            ->whereHas('category', function ($q) {
                $q->where('status', 1)
                    ->where(function ($q) {
                        $q->where('parent_id', 0)
                            ->orWhereHas('parent', fn ($p) => $p->where('status', 1));
                    });
            });
    }
    public function scopeServableIn($query, array $zoneIds, mixed $moduleId = null)
    {
        return $query
            ->whereHas('module.zones', fn ($q) => $q->whereIn('zones.id', $zoneIds))
            ->whereHas('store', function ($q) use ($zoneIds, $moduleId) {
                $q->whereIn('zone_id', $zoneIds)
                    ->whereHas('zone.modules', fn ($zone) => $zone->when($moduleId, fn ($m) => $m->where('modules.id', $moduleId)));
            });
    }

    public function scopeOrderCountsByCommonCondition($query, array $zoneIds, mixed $moduleId = null, string $type = 'all')
    {
        $conditionItems = \Illuminate\Support\Facades\DB::table('pharmacy_item_details')
            ->select('common_condition_id', 'item_id')
            ->whereNotNull('common_condition_id')
            ->distinct();

        return $query->withoutGlobalScope('translate')
            ->joinSub($conditionItems, 'condition_items', 'condition_items.item_id', '=', 'items.id')
            ->where(fn ($q) => $q->servableIn($zoneIds, $moduleId)->active()->type($type))
            ->groupBy('condition_items.common_condition_id')
            ->selectRaw('condition_items.common_condition_id as common_condition_id, SUM(items.order_count) as order_count');
    }

    public function scopePopular($query)
    {
        return $query->orderBy('order_count', 'desc');
    }
    public function scopeApproved($query)
    {
        return $query->where('is_approved', 1);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class)->latest();
    }

    public function whislists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }


        public function rating()
    {
        return $this->hasMany(Review::class, 'item_id')
            ->select(
                'item_id',
                DB::raw('AVG(rating) as average'),
                DB::raw('COUNT(*) as rating_count'),
                DB::raw('COUNT(CASE WHEN comment IS NOT NULL THEN 1 END) as review_count')
            )
            ->groupBy('item_id');
    }

    public function flashSaleItems()
    {
        return $this->hasMany(FlashSaleItem::class);
    }

    public function getUnitTypeAttribute()
    {
        return $this->unit ? $this->unit->unit : null;
    }

    public function getNameAttribute($value)
    {
        return $this->translatedAttribute('name', $value);
    }

    public function getDescriptionAttribute($value)
    {
        return $this->translatedAttribute('description', $value);
    }
    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('product', 'image', $this->image, 'default', 'product');
    }
    public function getImagesFullUrlAttribute()
    {
        $images = [];
        $value = is_array($this->images)
            ? $this->images
            : ($this->images && is_string($this->images) && $this->isValidJson($this->images)
                ? json_decode($this->images, true)
                : []);
        if ($value) {
            foreach ($value as $item) {
                $item = is_array($item) ? $item : (is_object($item) && get_class($item) == 'stdClass' ? json_decode(json_encode($item), true) : ['img' => $item, 'storage' => 'public']);
                $images[] = Helpers::get_full_url('product', $item['img'], $item['storage'],'default');
            }
        }

        return $images;
    }

    private function isValidJson($string)
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }


    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function storeCategory()
    {
        return $this->belongsTo(StoreCategory::class, 'store_category_id');
    }

    public function pharmacy_item_details()
    {
        return $this->hasOne(PharmacyItemDetails::class, 'item_id');
    }
    public function ecommerce_item_details()
    {
        return $this->hasOne(EcommerceItemDetails::class, 'item_id');
    }

    public function orders()
    {
        return $this->hasMany(OrderDetail::class);
    }

    protected static function booted()
    {
        static::addGlobalScope(new StoreScope);

        static::addGlobalScope(new ZoneScope);

    }


    public function scopeType($query, $type)
    {
        if ($type == 'veg') {
            return $query->where('veg', true);
        } else if ($type == 'non_veg') {
            return $query->where('veg', false);
        }
        return $query;
    }

    public function scopeAvailable($query, $time)
    {
        $query->where(function ($q) use ($time) {
            $q->where('available_time_starts', '<=', $time)->where('available_time_ends', '>=', $time);
        });
    }
    public function scopeUnAvailable($query, $time)
    {
        $query->whereNot(function ($q) use ($time) {
            $q->where('available_time_starts', '<=', $time)->where('available_time_ends', '>=', $time);
        });
    }

    public function scopeAvailableNow($query)
    {
        $now = now()->format('H:i:s');

        return $query->where(function ($q) use ($now) {
            $q->whereRaw('(available_time_starts < available_time_ends AND TIME(?) BETWEEN available_time_starts AND available_time_ends)', [$now])
                ->orWhereRaw('(available_time_starts > available_time_ends AND (TIME(?) >= available_time_starts OR TIME(?) <= available_time_ends))', [$now, $now]);
        });
    }

    public function getIsAvailableNowAttribute(): bool
    {
        $start = $this->available_time_starts;
        $end = $this->available_time_ends;
        if (empty($start) || empty($end)) {
            return true;
        }
        $now = now()->format('H:i:s');
        return $start <= $end
            ? ($now >= $start && $now <= $end)
            : ($now >= $start || $now <= $end);
    }

    public function scopeApplyFilters($query, array $filters)
    {
        return $query
            ->when(isset($filters['store_category_id']) && is_numeric($filters['store_category_id']), function ($q) use ($filters) {
                $q->where('store_category_id', $filters['store_category_id']);
            })
            ->when(isset($filters['filter_by']) && is_array($filters['filter_by']), function ($q) use ($filters) {
                foreach ($filters['filter_by'] as $item) {
                    if ($item == 'free_delivery') {
                        $q->whereHas('store', function ($query) {
                            $query->where('free_delivery', 1);
                        });
                    } elseif ($item == 'discounted' || $item == 'offers') {
                        $q->discounted();
                    } elseif ($item == 'popular') {
                        $q->reorder()->orderBy('order_count', 'desc');
                    } elseif ($item == 'new_arrivals') {
                        $q->reorder()->latest();
                    } elseif ($item == 'top_rated') {
                        $q->where('avg_rating', '>', 0)->reorder()->orderBy('avg_rating', 'desc');
                    } elseif ($item == 'veg') {
                        $q->where('veg', 1);
                    } elseif ($item == 'non_veg') {
                        $q->where('veg', 0);
                    } elseif ($item == 'currently_available') {
                        $q->available(now()->format('H:i:s'));
                    } elseif ($item == 'halal') {
                        $q->where('is_halal', 1);
                    } elseif ($item == 'high') {
                        $q->reorder()->orderBy('price', 'desc');
                    } elseif ($item == 'low') {
                        $q->reorder()->orderBy('price', 'asc');
                    } elseif ($item == 'nearby') {
                        $longitude = request()->header('longitude') ?? request('longitude');
                        $latitude = request()->header('latitude') ?? request('latitude');
                        if ($longitude && $latitude) {
                            $q->selectSub(function ($query) use ($longitude, $latitude) {
                                $query->selectRaw('ST_Distance_Sphere(point(longitude, latitude), point(?, ?))', [$longitude, $latitude])
                                    ->from('stores')->whereColumn('stores.id', 'items.store_id')->limit(1);
                            }, 'store_distance')->reorder()->orderBy('store_distance', 'asc');
                        }
                    } elseif ($item == 'verified_seller') {
                        $q->whereHas('store.storeConfig', function ($query) {
                            $query->where('verified_seller', 1);
                        });
                    }
                }
            });
    }

    public function scopeFilterList($query,$filter,$min,$max,$category_ids,$rating_count,$withCount,$search,$store_category_id = null){
        $key = $search ? explode(' ', $search ?? ''):[];

        $query =  $query->withCount(array_unique($withCount));

           $query = $query->when(isset($category_ids) && (count($category_ids)>0), function($query)use($category_ids){
                $query->whereHas('category',function($q)use($category_ids){
                    $q->where(function ($q) use ($category_ids) {
                            $q->whereIn('id', $category_ids)->orWhereIn('parent_id', $category_ids);
                        });
                    });
            })
            ->when(is_numeric($store_category_id), function($query)use($store_category_id){
                $query->where('store_category_id', $store_category_id);
            })
            ->when($search, function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->where('name', 'like', "%{$value}%");
                    }
                });
            })
            ->when($max, function($query)use($min,$max){
                $query->whereBetween('price',[$min,$max]);
            })
            ->when($rating_count, function($query) use ($rating_count){
                $query->where('avg_rating', '>=' , $rating_count);
            })
            ->when($filter && in_array('top_rated',$filter),function ($qurey){
                $qurey->orderByDesc('reviews_count');
            })
            ->when($filter && in_array('most_loved',$filter),function ($qurey){
                $qurey->having('whislists_count' ,'>',0);
            })
            ->when($filter && in_array('popular',$filter),function ($qurey){
                  $qurey->popular();
            })
            ->when($filter && in_array('available_now', $filter) && !in_array('un_available_now', $filter), function ($query) {
                $query->Available(now()->format('H:i:s'));
            })
            ->when($filter && in_array('un_available_now', $filter)&& !in_array('available_now', $filter), function ($query) {
                $query->UnAvailable(now()->format('H:i:s'));
            })
            ->when($filter && in_array('latest',$filter),function ($qurey){
                $qurey->whereBetween('created_at', [now()->subYear(), now()]);            })
           ->when($filter && in_array('high',$filter),function ($qurey){
                $qurey->orderByDesc('price');
            })
            ->when($filter && in_array('low',$filter),function ($qurey){
                $qurey->orderBy('price');
            })
            ->when($filter && in_array('z_to_a',$filter),function ($qurey){
                $qurey->orderByDesc('name');
            })
            ->when($filter && in_array('a_to_z',$filter),function ($qurey){
                $qurey->orderBy('name');
            });

            return $query;
    }

    public function scopeApplySorting($query, $sortBy)
    {
        $sortBy = self::normalizeSortValue($sortBy);

        return $query->when($sortBy && $sortBy !== 'default', function ($q) use ($sortBy) {
            if ($sortBy == 'fast_delivery') {
                $q->reorder()->orderBy(function ($query) {
                    $query->selectRaw('IF(((select count(*) from `store_schedule` where `stores`.`id` = `store_schedule`.`store_id` and `store_schedule`.`day` = '.now()->dayOfWeek.' and `store_schedule`.`opening_time` < "'.now()->format('H:i:s').'" and `store_schedule`.`closing_time` >"'.now()->format('H:i:s').'") > 0), true, false)')
                        ->from('stores')->whereColumn('stores.id', 'items.store_id')->limit(1);
                }, 'desc')
                ->orderBy(function ($query) {
                    $query->selectRaw("
                        CASE
                            WHEN delivery_time LIKE '%hour%'
                                THEN CAST(SUBSTRING_INDEX(delivery_time,'-',1) AS UNSIGNED) * 60
                            WHEN delivery_time LIKE '%min%'
                                THEN CAST(SUBSTRING_INDEX(delivery_time,'-',1) AS UNSIGNED)
                            ELSE CAST(SUBSTRING_INDEX(delivery_time,'-',1) AS UNSIGNED)
                        END")
                        ->from('stores')->whereColumn('stores.id', 'items.store_id')->limit(1);
                }, 'asc');
            } elseif ($sortBy == 'a_to_z') {
                $q->reorder()->orderBy('name', 'asc');
            } elseif ($sortBy == 'z_to_a') {
                $q->reorder()->orderBy('name', 'desc');
            } elseif ($sortBy == 'price_low_to_high') {
                $q->reorder()->orderBy('price', 'asc');
            } elseif ($sortBy == 'price_high_to_low') {
                $q->reorder()->orderBy('price', 'desc');
            } elseif ($sortBy == 'distance') {
                $longitude = request()->header('longitude') ?? request('longitude');
                $latitude = request()->header('latitude') ?? request('latitude');

                if ($longitude && $latitude) {
                    $q->reorder()
                        ->selectSub(function ($query) use ($longitude, $latitude) {
                            $query->selectRaw(
                                'ST_Distance_Sphere(point(longitude, latitude), point(?, ?))',
                                [$longitude, $latitude]
                            )
                            ->from('stores')
                            ->whereColumn('stores.id', 'items.store_id')
                            ->limit(1);
                        }, 'distance')
                        ->orderBy('distance', 'asc');
                }
            } elseif ($sortBy == 'high_rated') {
                $q->reorder()->orderBy('avg_rating', 'desc');
            }
        });
    }

    public function scopeApplyRating($query, $request)
    {
        if (!$request) {
            return $query;
        }

        $ratingPlus = $request->rating_plus ?? null;
        if ($ratingPlus && !is_array($ratingPlus)) {
            $ratingPlus = str_getcsv(trim($ratingPlus, "[]"), ',');
        }
        $ratingPlus = is_array($ratingPlus)
            ? array_values(array_filter(array_map('intval', $ratingPlus), fn ($v) => $v > 0))
            : [];

        return $query->when($request->rating == 1, function ($query) {
            return $query->has('reviews')->withCount('reviews')->orderBy('reviews_count', 'desc');
        })
        ->when(!empty($ratingPlus), function ($query) use ($ratingPlus) {
            $query->where('avg_rating', '>=', min($ratingPlus));
        })
        ->when($request->rating_count, function ($query) use ($request) {
            $query->where('avg_rating', '>=', $request->rating_count);
        })
        ->when(($request->rating_1 == 1 || $request->rating_1_plus == 1), function ($query) {
            $query->where('avg_rating', '>=', 1);
        })
        ->when(($request->rating_2 == 1 || $request->rating_2_plus == 1), function ($query) {
            $query->where('avg_rating', '>=', 2);
        })
        ->when(($request->rating_3 == 1 || $request->rating_3_plus == 1), function ($query) {
            $query->where('avg_rating', '>=', 3);
        })
        ->when(($request->rating_4 == 1 || $request->rating_4_plus == 1), function ($query) {
            $query->where('avg_rating', '>=', 4);
        })
        ->when($request->rating_3_plus == 1, function ($query) {
            $query->where('avg_rating', '>', 3);
        })
        ->when(($request->rating_4_plus == 1 && !($request->rating_5 == 1 || $request->rating_3_plus == 1) || ($request->rating_4_plus == 1 && $request->rating_5 == 1 && $request->rating_3_plus != 1)), function ($query) {
            $query->where('avg_rating', '>', 4);
        })
        ->when($request->rating_5 == 1 && !($request->rating_4_plus == 1 || $request->rating_3_plus == 1), function ($query) {
            $query->where('avg_rating', '>=', 5);
        });
    }

    public function scopeApplyPriceRange($query, $request)
    {
        if (!$request) {
            return $query;
        }

        $price = $request->price ?? null;
        if (is_string($price)) {
            $price = str_replace(['[', ']'], '', $price);
            $price = explode(',', $price);
        }

        return $query->when($price && count($price) == 2 && is_numeric($price[0]) && is_numeric($price[1]), function ($query) use ($price) {
            $query->whereBetween('price', [round($price[0], 2), round($price[1], 2)]);
        })->when($request->min_price, function ($query) use ($request) {
            $query->where('price', '>=', $request->min_price);
        })->when($request->max_price, function ($query) use ($request) {
            $query->where('price', '<=', $request->max_price);
        });
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
    public function allergies()
    {
        return $this->belongsToMany(Allergy::class);
    }
    public function generic()
    {
        return $this->belongsToMany(GenericName::class, 'item_generic_names');
    }
    public function nutritions()
    {
        return $this->belongsToMany(Nutrition::class);
    }
    protected static function boot()
    {
        parent::boot();
        static::created(function ($item) {
            $item->slug = $item->generateSlug($item->name);
            $item->save();
        });
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
            self::recordStorageDisk($model, 'images', 'images');
            self::recordStorageDisk($model, 'video', 'video');

            $bundles = app(BundleService::class);

            if ($bundles->itemPricingChanged($model)) {
                $bundles->repriceLinesForItem($model);
            }
        });
    }

    public function taxVats()
    {
        return $this->morphMany(Taxable::class, 'taxable');
    }

    public function seoData(){
        return $this->hasOne(ItemSeoData::class,'item_id');
    }


    public function users()
    {
        return $this->morphToMany(User::class ,'visitor_log');
    }
}
