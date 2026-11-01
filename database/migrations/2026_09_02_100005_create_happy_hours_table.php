<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An admin-run, store-wide percentage discount that only applies inside a schedule.
 *
 * Scoped by MODULE, and by nothing else. The source scoped by zone because it had one vertical and
 * no module concept; here the whole admin panel is module-scoped through the header's switcher, so
 * a happy hour belongs to whichever module the admin was in -- exactly as a campaign or a coupon
 * does. module_id is not nullable: "all modules" is not offered, because a cross-module happy hour
 * is meaningless under the capability matrix.
 *
 * There is deliberately no zone_id. The panel has no zone context to inherit one from, and which
 * zones a happy hour actually reaches follows from the stores that enrol in it -- a store carries
 * its own zone, so the reach is expressed once, in the enrolments, rather than twice.
 *
 * Two happy hours in the same module may not overlap in time. That rule is enforced in the
 * controller rather than the schema -- it depends on duration_type, and a daily window has to be
 * expanded before it can be compared to a custom one -- but it is why module and status are
 * indexed together here, and why the save path takes a lock keyed on the module.
 *
 * Three schedule shapes share this table:
 *   daily   -- start_date..end_date, one time range per day
 *   weekly  -- the same, restricted to weekly_days
 *   custom  -- explicit dates with their own times, in custom_days / custom_times
 * Whichever is chosen, the resolved windows are materialised into happy_hour_dates so the runtime
 * asks one indexed question instead of re-deriving a schedule on every price calculation.
 *
 * is_permanent means the window repeats with no end date. min_order_amount is nullable because
 * the requirement is optional -- the form gates it behind a toggle and the list renders "N/A".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('happy_hours')) {
            return;
        }

        Schema::create('happy_hours', function (Blueprint $table) {
            $table->id();

            // No FK constraint -- see bogo_offer_items' own note on why a fresh install must not
            // depend on migration run order. Already indexed below via (module_id, status).
            $table->foreignId('module_id');

            $table->string('title');
            $table->string('short_description', 255)->nullable();

            // Percentage only. A happy hour has no flat-amount form.
            $table->decimal('discount', 24, 2)->default(0);
            $table->decimal('min_order_amount', 24, 2)->nullable();

            $table->boolean('is_permanent')->default(0);

            // 'daily', 'weekly' or 'custom'.
            $table->string('duration_type', 10)->default('daily');
            $table->json('weekly_days')->nullable();
            $table->json('custom_days')->nullable();
            $table->json('custom_times')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->string('cover_image')->nullable();
            $table->string('icon')->nullable();

            $table->string('slug')->nullable();
            $table->boolean('status')->default(1);
            $table->unsignedBigInteger('admin_id')->nullable();

            $table->timestamps();

            // The overlap check and the listing both ask "what runs in this module".
            $table->index(['module_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('happy_hours');
    }
};
