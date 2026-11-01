<?php

namespace App\Models;

use App\Support\Notification\NotificationGate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saved(fn () => NotificationGate::flush());
        static::deleted(fn () => NotificationGate::flush());
    }
}
