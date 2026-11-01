<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Cache\ApiCache;

class SocialMedia extends Model
{
    use HasFactory, InvalidatesCacheTrait;

    protected static array $cacheTags = ['reference'];

    protected $casts = [
        'status'        => 'integer',
        'active_status' => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', '=', 1);
    }

    protected static function booted(): void
    {

    }
}
