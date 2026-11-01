<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasUuidTrait;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;

class Setting extends Model
{
    use HasFactory;
    use HasUuidTrait;

    protected $table = 'addon_settings';

    protected $casts = [
        'live_values' => 'array',
        'test_values' => 'array',
        'is_active' => 'integer',
    ];

    protected $fillable = ['key_name', 'live_values', 'test_values', 'settings_type', 'mode', 'is_active', 'additional_data'];

    protected static function boot()
    {
        parent::boot();
    }
}
