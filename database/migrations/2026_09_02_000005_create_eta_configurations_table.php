<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ETA configuration, per (zone, module) — port doc §11.1.
 *
 * The configuration decides how the estimated delivery time shown to a customer is arrived at.
 * `calculation_method` says where the TRAVEL part comes from:
 *
 *   distance_based       from the map's `delivery_duration`; `time_gap` widens the result into
 *                        the range the customer sees
 *   fixed_delivery_time  from the store's own delivery time, so the range is already given and
 *                        no gap is kept
 *
 * **Every column here is minutes.** The two buffers are added whichever method is chosen, and
 * `minimum_delivery_time` is a FLOOR the estimate never falls below — not a term added to it.
 *
 * Keyed on (zone, module), not zone, unlike the StackFood source (N5). The module half lives in
 * `eta_configuration_module` because a configuration connects many; see that migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eta_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->foreignId('zone_id');
            // See EtaConfiguration::METHOD_*
            $table->string('calculation_method', 30)->default('distance_based')
                ->comment('distance_based | fixed_delivery_time');
            $table->unsignedSmallInteger('minimum_delivery_time')->nullable();
            $table->unsignedSmallInteger('preparation_buffer')->nullable();
            $table->unsignedSmallInteger('transit_buffer')->nullable();
            // Distance based only — the other method takes its maximum from the store.
            $table->unsignedSmallInteger('time_gap')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            // The read every estimate makes: this zone's active configuration.
            $table->index(['zone_id', 'status'], 'eta_configurations_zone_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eta_configurations');
    }
};
