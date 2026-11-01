<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriorityList extends Model
{
    use HasFactory, InvalidatesCacheTrait;

    protected static array $cacheTags = ['priority_setting'];

    protected $guarded = ['id'];
    protected static function boot()
    {
        parent::boot();



    }
}
