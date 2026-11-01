<?php

namespace App\Models;

use App\Traits\Model\DemoMaskableTrait;
use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    use DemoMaskableTrait;
    
    protected $guarded = ['id'];

    protected $casts = [
        'user_id' => 'integer',
        'zone_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
}
