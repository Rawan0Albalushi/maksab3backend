<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use App\Traits\Model\HasTranslationsTrait;

/**
 * Class Attribute
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Attribute extends Model
{
    use HasFactory, HasTranslationsTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    /**
     * @return MorphMany
     */
    /**
     * @param $value
     * @return mixed
     */
    public function getNameAttribute($value)
    {
        return $this->translatedAttribute('name', $value);
    }

    /**
     * @return void
     */
}
