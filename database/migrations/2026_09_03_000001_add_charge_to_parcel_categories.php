<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A parcel category carries ONE charge now, and it is ADDITIVE — owner decision 2026-09-03,
 * and the answer to the parcel brief's §1 (model (a)).
 *
 * Before: a category priced the parcel outright, through its own `parcel_per_km_shipping_charge`
 * and `parcel_minimum_shipping_charge`. The delivery rule was not consulted at all.
 *
 * After: the delivery rule prices the parcel like any other order, and this single charge is
 * ADDED on top — the same shape as the weight band and the dimension class.
 *
 * SEEDED FROM THE MINIMUM, not from zero and not from the per-km rate. The minimum is a flat
 * amount the admin has already typed for that category, and it is what a short parcel trip
 * already cost — so on this install three of the six categories reprice by nothing at all, and
 * the other three only on longer trips, where the per-km used to take over. Seeding zero would
 * have silently made every parcel free.
 *
 * The two old columns are LEFT IN PLACE and their values untouched, so a rollback loses nothing.
 * Nothing reads them after this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_categories', function (Blueprint $table) {
            $table->decimal('charge', 10, 2)->default(0);
        });

        DB::table('parcel_categories')->update([
            'charge' => DB::raw('COALESCE(parcel_minimum_shipping_charge, 0)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('parcel_categories', function (Blueprint $table) {
            $table->dropColumn('charge');
        });
    }
};
