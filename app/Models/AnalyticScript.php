<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\CentralLogics\Helpers;

class AnalyticScript extends Model
{
    use HasFactory, InvalidatesCacheTrait;

    protected static array $cacheTags = ['analytic_script'];
    protected $guarded = ['id'];

    protected $casts = [
        'name' => 'string',
        'type' => 'string',
        'script_id' => 'string',
        'script' => 'string',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    protected static function boot(): void
    {
        parent::boot();

    }
}
