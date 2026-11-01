<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\Traits\Model\HasProductVideoPreviewTrait;
use App\Scopes\ZoneScope;
use App\Scopes\StoreScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;
use Modules\TaxModule\Entities\Taxable;
use App\Traits\Model\HasStorageTrait;
use App\Traits\Model\HasTranslationRelationTrait;
use App\Traits\System\SidebarCountsTrait;

class TempProduct extends Model
{
    use HasFactory, HasProductVideoPreviewTrait, HasStorageTrait, HasTranslationRelationTrait, SidebarCountsTrait;
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
        'veg'=>'integer',
        'images'=>'array',
        'module_id'=>'integer',
        'item_id'=>'integer',
        'is_rejected'=>'integer',
        'stock'=>'integer',
    ];
    protected $guarded = ['id'];
    protected $appends = ['image_full_url','images_full_url', 'video_full_url', 'video_size', 'video_preview_type', 'video_embed_url', 'video_preview_url', 'video_thumbnail_url', 'video_preview_modal_type', 'video_preview_modal_url', 'has_video_preview', 'has_video_source'];
    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('product', 'image', $this->image);
    }
    public function getImagesFullUrlAttribute(){
        $images = [];
        $value = is_array($this->images)
            ? $this->images
            : ($this->images && is_string($this->images) && $this->isValidJson($this->images)
                ? json_decode($this->images, true)
                : []);
        if ($value){
            foreach ($value as $item){
                $item = is_array($item)?$item:(is_object($item) && get_class($item) == 'stdClass' ? json_decode(json_encode($item), true):['img' => $item, 'storage' => 'public']);
                $images[] = Helpers::get_full_url('product',$item['img'],$item['storage']);
            }
        }

        return $images;
    }

    private function isValidJson($string)
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }


    public function scopeModule($query, $module_id)
    {
        return $query->where('module_id', $module_id);
    }
    public function scopeApproved($query)
    {
        return $query;
    }
    public function item(){
        return $this->belongsTo(Item::class,'item_id');
    }
    public function common_condition(){
        return $this->belongsTo(CommonCondition::class,'common_condition_id');
    }
    public function brand(){
        return $this->belongsTo(Brand::class,'brand_id');
    }

    public function pharmacy_item_details()
    {
        return $this->hasOne(PharmacyItemDetails::class, 'temp_product_id');
    }
    public function ecommerce_item_details()
    {
        return $this->hasOne(EcommerceItemDetails::class, 'temp_product_id');
    }

    public function scopeType($query, $type)
    {
        if($type == 'veg')
        {
            return $query->where('veg', true);
        }
        else if($type == 'non_veg')
        {
            return $query->where('veg', false);
        }

        return $query;
    }
    public function unit()
    {
        return $this->belongsTo(Unit::class,'unit_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class,'module_id');
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

    protected static function booted()
    {
        static::addGlobalScope(new StoreScope);
        static::addGlobalScope(new ZoneScope);
    }
    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
            self::recordStorageDisk($model, 'images', 'images');
            self::recordStorageDisk($model, 'video', 'video');
        });

    }
       public function taxVats()
    {
        return $this->morphMany(Taxable::class, 'taxable');
    }

    public function seoData(){
        return $this->hasOne(ItemSeoData::class,'temp_item_id');
    }
}
