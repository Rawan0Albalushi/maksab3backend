<?php

namespace App\Models;

use App\Traits\Model\DemoMaskableTrait;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\HasStorageTrait;

class VendorEmployee extends Authenticatable
{
    use Notifiable, DemoMaskableTrait, HasStorageTrait;

    protected $fillable = ['remember_token','login_remember_token'];

    protected $casts = [
        'employee_role_id' => 'integer',
        'vendor_id' => 'integer',
        'store_id' => 'integer',
        'is_logged_in' => 'boolean',
    ];

    protected $hidden = [
        'password',
        'auth_token',
        'remember_token',
    ];
    public function getImageFullUrlAttribute()
    {
        return $this->storageFullUrl('vendor', 'image', $this->image);
    }
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function role(){
        return $this->belongsTo(EmployeeRole::class,'employee_role_id');
    }

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'image', 'image');
        });
    }
}
