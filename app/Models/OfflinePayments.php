<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\System\SidebarCountsTrait;

class OfflinePayments extends Model
{
    use HasFactory, SidebarCountsTrait;
    protected $casts = [
        'order_id'=>'integer',
    ];
    protected $guarded = ['id'];
}
