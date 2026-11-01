<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\Model\HasTranslationsTrait;

class AutomatedMessage extends Model
{
    use HasFactory, HasTranslationsTrait;
    protected $guarded = ['id'];
    protected $casts = [
        'id' => 'integer',
        'status' => 'boolean',
    ];

    public function getMessageAttribute($value)
    {
        return $this->translatedAttribute('message', $value);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeCustomer($query) {
        return $query->where('question_for', 'customer');
    }

    public function scopeRider($query) {
        return $query->withoutGlobalScope('customer_only')->where('question_for', 'rider');
    }

    protected static function booted()
    {
        static::addGlobalScope('customer_only', function (Builder $builder) {
            $builder->customer();
        });
    }
}
