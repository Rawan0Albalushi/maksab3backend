<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A store's enrolment in a happy hour. Renamed from the source's happy_hour_restaurant.
 *
 * The same approval shape as bogo_offer_store, and deliberately so -- both features run the same
 * request / approve / deny / withdraw conversation, and the shared vocabulary is what lets one set
 * of notification rows and one enrolment trait serve them both.
 *
 * What it does NOT carry is an items table. Joining a happy hour is accepting a rate on everything
 * the store sells, so there is nothing to freeze; joining a BOGO offer means building a bundle,
 * which is why only that side has bogo_offer_items hanging off it. That difference is also why
 * the vendor panel needs a join drawer for BOGO and a single click for happy hour, and why a
 * rejected BOGO enrolment can be amended and resubmitted while a rejected happy hour cannot --
 * there is nothing to amend.
 *
 * There is no combination_signature here for the same reason: with no bundle to describe, a store
 * either is in the offer or is not, and the unique key below says so.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('happy_hour_store')) {
            return;
        }

        Schema::create('happy_hour_store', function (Blueprint $table) {
            $table->id();

            // No FK constraints -- see bogo_offer_items' own note on why a fresh install must not
            // depend on migration run order. Both columns are already indexed below.
            $table->foreignId('happy_hour_id');
            $table->foreignId('store_id');

            $table->string('status', 20)->default('pending');
            $table->string('rejection_reason', 255)->nullable();

            // 'admin' (an invitation) or 'vendor' (a request to join).
            $table->string('requested_by', 10)->default('vendor');

            // Which SIDE said no, not which user: 'admin' or 'store', null until one does.
            $table->string('rejected_by', 10)->nullable();

            $table->boolean('checked')->default(0);
            $table->timestamp('joined_at')->nullable();

            $table->timestamps();

            $table->unique(['happy_hour_id', 'store_id']);
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('happy_hour_store');
    }
};
