<?php

namespace App\Models;

use App\Support\Notification\NotificationGate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreNotificationSetting extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(fn (self $setting) => NotificationGate::forgetStore($setting->store_id));
        static::deleted(fn (self $setting) => NotificationGate::forgetStore($setting->store_id));
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
