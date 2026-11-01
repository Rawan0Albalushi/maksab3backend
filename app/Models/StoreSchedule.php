<?php

namespace App\Models;

use App\Traits\Model\InvalidatesCacheTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSchedule extends Model
{
    use HasFactory, InvalidatesCacheTrait;

    protected static array $cacheTags = ['store'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'store_schedule';

    protected $casts = [
        'day'=>'integer',
        'store_id'=>'integer',
    ];

    protected $fillable = ['store_id','day','opening_time','closing_time'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
