<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasTranslationsTrait;

class SurgePrice extends Model
{
    use HasFactory, HasTranslationsTrait;

    protected $casts = [
        'custom_days' => 'array',
        'custom_times' => 'array',
        'weekly_days' => 'array',
        'module_ids' => 'array',
        'customer_note_status' => 'integer',
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function details()
    {
        return $this->hasMany(SurgePriceDate::class, 'surge_price_id');
    }

    public function getSurgePriceNameAttribute($value)
    {
        return $this->translatedAttribute('surge_price_name', $value);
    }
    public function getCustomerNoteAttribute($value)
    {
        return $this->translatedAttribute('customer_note', $value);
    }

    public function scopeActive($query)
    {
        return $query->where('status', '=', 1);
    }

}
