<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\HasStorageTrait;

class UserInfo extends Model
{
    use HasFactory, HasStorageTrait;

    protected $casts = [
        'user_id' => 'integer',
        'vendor_id' => 'integer',
        'deliveryman_id' => 'integer',
        'admin_id' => 'integer'
    ];
    protected $appends = ['image_full_url'];

    /**
     * image_full_url is appended, so every serialized row reads one of these three depending on
     * which owner column is set. Which one is not known until the row is in hand, so all three
     * are loaded with it rather than left to lazy-load per row.
     */
    protected $with = ['user.storage', 'vendor.stores.storage', 'delivery_man.storage'];

    public function getImageFullUrlAttribute(){
        if ($this->user_id){
            return $this->user?->image_full_url;
        }elseif ($this->vendor_id){
            return $this->vendor?->stores->first()?->logo_full_url;
        }elseif ($this->deliveryman_id){
            return $this->delivery_man?->image_full_url;
        }
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function delivery_man()
    {
        return $this->belongsTo(DeliveryMan::class, 'deliveryman_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });

    }
}
