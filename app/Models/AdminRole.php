<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use App\Traits\Model\HasTranslationsTrait;

/**
 * Class AdminRole
 *
 * @property int $id
 * @property string $name
 * @property string|null $modules
 * @property bool $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AdminRole extends Model
{
    use HasFactory, HasTranslationsTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'modules',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * @return MorphMany
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Admin::class, 'role_id');
    }

    /**
     * @param $value
     * @return mixed
     */
    public function getNameAttribute($value): mixed
    {
        return $this->translatedAttribute('name', $value);
    }

    /**
     * @return void
     */
}
