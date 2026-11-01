<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasTranslationRelationTrait;

class NotificationMessage extends Model
{
    use HasFactory, HasTranslationRelationTrait;
    protected $guarded = ['id'];
    public function getMessageAttribute($value)
    {
        return $this->translatedAttribute($this->key, $value);
    }
}
