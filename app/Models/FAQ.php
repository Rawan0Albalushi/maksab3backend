<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasTranslationsTrait;
class FAQ extends Model
{
    use HasFactory, HasTranslationsTrait;
    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'integer',
        'status' => 'integer',
        'faqable_id' => 'integer',
    ];


    public function faqable()
    {
        return $this->morphTo();
    }

    public function getQuestionAttribute($value)
    {
        return $this->translatedAttribute('question', $value);
    }
    public function getAnswerAttribute($value)
    {
        return $this->translatedAttribute('answer', $value);
    }

}
