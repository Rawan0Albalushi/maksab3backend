<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional Delivery Charge, per (zone, module) — the saver delivery options.
 *
 * Two offers a customer can pick instead of standard delivery:
 *
 *   express         pay MORE, wait LESS   -> express_extra_charge, express_reduce_delivery_time
 *   slightly_delay  pay LESS, wait MORE   -> delay_reduce_charge,  delay_add_delivery_time
 *
 * The names read as the design labels them, so the column a field writes is obvious from the
 * screen: "Add Extra Charge" and "Reduce Delivery Time" under Express; "Reduce Charge" and
 * "Add Extra Delivery Time" under Slightly Delay.
 *
 * **Both time columns are minutes.** The form offers Min/Hour, and the hour choice is multiplied
 * out before it is stored, so nothing downstream has to carry a unit alongside the number.
 *
 * Replaces `module_zone_delivery_options`, which held one row per (module, zone, type) and was
 * edited inline on the Module Setup screen. That table could not express the design at all: it
 * had no place for the express vehicle filter, and no setup identity, so two modules configured
 * separately were indistinguishable from two configured together. The rows are moved across by
 * 2026_09_06_000004; the fee engine keeps reading through one method, so the pipeline itself is
 * untouched (N1).
 *
 * Keyed on (zone, module) per N5. The module half lives in `additional_delivery_charge_module`
 * because one setup connects many — see that migration for why uniqueness is an overlap test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_delivery_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id');

            // Express: the customer pays this much extra to have this many minutes taken off.
            $table->decimal('express_extra_charge', 10, 2)->nullable();
            $table->unsignedSmallInteger('express_reduce_delivery_time')->nullable();

            // Slightly delay: the customer waits this many extra minutes for this much off.
            $table->decimal('delay_reduce_charge', 10, 2)->nullable();
            $table->unsignedSmallInteger('delay_add_delivery_time')->nullable();

            $table->boolean('status')->default(true);
            $table->timestamps();

            // The read every quote makes: this zone's active setup.
            $table->index(['zone_id', 'status'], 'adc_zone_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_delivery_charges');
    }
};
