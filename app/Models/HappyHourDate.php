<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One materialised day of a happy hour's schedule.
 *
 * HappyHour stores the schedule as a rule; these rows are the answer. Expanding the rule on every
 * price calculation would mean parsing JSON and comparing dates for each item on a menu, so the
 * expansion happens once at save and the runtime asks a single indexed question instead.
 *
 * module_id is denormalised from the parent because it is exactly what the lookup filters on, and
 * carrying it here keeps that query off a join. There is no zone: a happy hour is module-scoped,
 * and which zones it reaches follows from the stores that enrol in it.
 */
class HappyHourDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'happy_hour_id',
        'module_id',
        'applicable_date',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'happy_hour_id' => 'integer',
        'module_id' => 'integer',
        'applicable_date' => 'date',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function happyHour()
    {
        return $this->belongsTo(HappyHour::class);
    }

    /**
     * Windows live right now for this module.
     *
     * This is the query the table exists to answer, and the composite index matches its column
     * order. Module is the whole of the scope: a grocery happy hour must never surface for a food
     * order, and nothing narrower than the module is asked here.
     */
    public function scopeLiveFor(Builder $query, $moduleId): Builder
    {
        $now = now();

        return $query->where('module_id', $moduleId)
            ->where('status', 1)
            ->whereDate('applicable_date', $now->toDateString())
            ->whereTime('start_time', '<=', $now->format('H:i:s'))
            ->whereTime('end_time', '>=', $now->format('H:i:s'));
    }
}
