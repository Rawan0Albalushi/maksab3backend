<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use App\Traits\Model\HasTranslationsTrait;

/**
 * Class WalletBonus
 *
 * @property int $id
 * @property string $title
 * @property array $translations
 * @property string|null $description
 * @property string $bonus_type
 * @property float $bonus_amount
 * @property float $minimum_add_amount
 * @property float $maximum_bonus_amount
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WalletBonus extends Model
{
    use HasFactory, HasTranslationsTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'description',
        'bonus_type',
        'bonus_amount',
        'minimum_add_amount',
        'maximum_bonus_amount',
        'start_date',
        'end_date',
        'status',
    ];

    /**
     * @var string[]
     */
    protected $casts = [
        'bonus_amount' => 'float',
        'minimum_add_amount' => 'float',
        'maximum_bonus_amount' => 'float',
        'status' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * @return MorphMany
     */
    /**
     * @param $value
     * @return mixed
     */
    public function getTitleAttribute($value): mixed
    {
        return $this->translatedAttribute('title', $value);
    }

    /**
     * @param $value
     * @return mixed
     */
    public function getDescriptionAttribute($value): mixed
    {
        return $this->translatedAttribute('description', $value);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeActive($query): mixed
    {
        return $query->where('status', '=', 1);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeRunning($query): mixed
    {
        return $query->where(function($q){
                $q->whereDate('end_date', '>=', date('Y-m-d'))->orWhereNull('end_date');
            })->where(function($q){
                $q->whereDate('start_date', '<=', date('Y-m-d'))->orWhereNull('start_date');
            });
    }

    /**
     * @return void
     */
}
