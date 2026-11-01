<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use App\Traits\Model\HasTranslationsTrait;

/**
 * Class Unit
 *
 * @property int $id
 * @property string $unit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Unit extends Model
{
    use HasFactory, HasTranslationsTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'unit',
    ];

    /**
     * @return MorphMany
     */
    /**
     * @param $value
     * @return mixed
     */
    public function getUnitAttribute($value)
    {
        return $this->translatedAttribute('unit', $value);
    }

    /**
     * @return void
     */
}
