<?php

namespace App\Models;

use App\Traits\Report\ReportFilterTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Model\HasTranslationsTrait;

class ProCustomerFaq extends Model
{
    use HasFactory, ReportFilterTrait, HasTranslationsTrait;

    protected $guarded = ['id'];

    protected $casts = [
        'priority' => 'integer',
        'status'   => 'integer',
    ];

    public function getQuestionAttribute($value)
    {
        return $this->translatedAttribute('pro_faq_question', $value);
    }

    public function getAnswerAttribute($value)
    {
        return $this->translatedAttribute('pro_faq_answer', $value);
    }

}
