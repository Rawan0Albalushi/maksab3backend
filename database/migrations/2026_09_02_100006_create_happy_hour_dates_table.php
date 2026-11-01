<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialised happy hour windows, one row per date the offer actually runs.
 *
 * happy_hours stores the schedule as a rule -- daily, weekly, or a list of custom dates. This
 * table stores the answer. Expanding the rule on every price calculation would mean parsing JSON
 * and comparing dates for each item on a menu; instead the expansion happens once at save and the
 * runtime asks a single indexed question: is there a row for this module, today, whose time range
 * contains now.
 *
 * module_id is denormalised from the parent on purpose. It is exactly the column the lookup filters
 * on, and carrying it here keeps that query off a join. There is no zone_id, for the same reason
 * the parent has none: a happy hour is module-scoped, and which zones it reaches follows from the
 * stores that enrol in it.
 *
 * status is per row rather than inherited, so a single date can be switched off without editing
 * the schedule that produced it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('happy_hour_dates')) {
            return;
        }

        Schema::create('happy_hour_dates', function (Blueprint $table) {
            $table->id();

            // No FK constraints -- see bogo_offer_items' own note on why a fresh install must not
            // depend on migration run order. Both columns are already indexed below.
            $table->foreignId('happy_hour_id');

            $table->foreignId('module_id');

            $table->date('applicable_date');
            $table->time('start_time');
            $table->time('end_time');

            $table->boolean('status')->default(1);

            $table->timestamps();

            // The one query this table exists to answer.
            $table->index(['module_id', 'applicable_date', 'status'], 'happy_hour_dates_lookup_index');

            // Re-materialising a schedule deletes its rows first.
            $table->index('happy_hour_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('happy_hour_dates');
    }
};
