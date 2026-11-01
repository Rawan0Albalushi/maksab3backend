<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErpApiToken extends Model
{
    protected $fillable = [
        'name',
        'api_key',
        'api_secret',
        'webhook_url',
        'is_active',
        'last_used_at',
        'webhook_last_dispatched_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'webhook_last_dispatched_at' => 'datetime',
    ];

    protected $hidden = [
        'api_secret',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeWithWebhook($query)
    {
        return $query->where('is_active', true)->whereNotNull('webhook_url');
    }
}
