<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parcel's own ETA numbers, alongside the general set an `eta_configurations` row already
 * carries for every other module it covers.
 *
 * Parcel never uses `calculation_method`/`preparation_buffer` — there is no store delivery-time
 * range to fall back to (a parcel pickup point doesn't quote one) and no kitchen to prepare
 * anything, so parcel is always "map travel time + transit buffer, widened by its own gap". Only
 * the three columns that formula needs are added; the other module types keep reading the
 * pre-existing four columns untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eta_configurations', function (Blueprint $table) {
            $table->unsignedSmallInteger('parcel_minimum_delivery_time')->nullable();
            $table->unsignedSmallInteger('parcel_transit_buffer')->nullable();
            $table->unsignedSmallInteger('parcel_time_gap')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('eta_configurations', function (Blueprint $table) {
            $table->dropColumn(['parcel_minimum_delivery_time', 'parcel_transit_buffer', 'parcel_time_gap']);
        });
    }
};
