<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use App\Traits\Model\HasStorageTrait;
use App\Traits\Model\HasTranslationRelationTrait;

class ReactTestimonial extends Model
{
    use HasFactory, HasStorageTrait, HasTranslationRelationTrait;
    protected $appends = ['reviewer_image_full_url','company_image_full_url'];
    public function getReviewerImageFullUrlAttribute()
    {
        return $this->storageFullUrl('reviewer_image', 'reviewer_image', $this->reviewer_image);
    }
    public function getCompanyImageFullUrlAttribute()
    {
        return $this->storageFullUrl('reviewer_company_image', 'company_image', $this->company_image);
    }
    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            self::recordStorageDisk($model, 'reviewer_image', 'reviewer_image');
            self::recordStorageDisk($model, 'company_image', 'company_image');
        });

    }
}
