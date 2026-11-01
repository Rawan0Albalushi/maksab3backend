<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasTranslationsTrait;

class OrderCancelReason extends Model
{
    use HasFactory, HasTranslationsTrait;
    protected $guarded = ['id'];
    protected $casts = [
        'id' => 'integer',
        'status' => 'integer'
    ];

    public function getReasonAttribute($value)
    {
        return $this->translatedAttribute('reason', $value);
    }

}
