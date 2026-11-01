<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\Model\SlugTrait;
use Modules\TaxModule\Entities\Taxable;
use App\Traits\Model\HasTranslationsTrait;

class AddonCategory extends Model
{
    use HasFactory, SlugTrait, HasTranslationsTrait;

    protected $guarded = ['id'];
     protected $casts = [
        'module_id' => 'integer',
        'status' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function Addons(): HasMany
    {
        return $this->hasMany(AddOn::class);
    }

    protected static function boot()
    {
        parent::boot();
        static::created(function ($category) {
            $category->slug = $category->generateSlug($category->name);
            $category->save();
        });
    }

    public function getNameAttribute($value): string
    {
        return $this->translatedAttribute('name', $value);
    }

    public function taxVats()
    {
        return $this->morphMany(Taxable::class, 'taxable');
    }


    
}
