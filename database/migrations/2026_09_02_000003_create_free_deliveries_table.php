<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free delivery, per (zone, module) — port doc §10.1.
 *
 * A setup frees the delivery charge for orders in its zone. `type` says on what terms:
 *
 *   all_store          every order in the zone/module, whatever it costs
 *   specific_criteria  only once the order reaches `minimum_order_amount`; a null amount
 *                      still covers every order (§10.3)
 *
 * The admin always bears the cost, so a freed order records `free_delivery_by = 'admin'` —
 * the same attribution the global setting this replaces already used (F3, §10.3).
 *
 * KEYED ON (zone, module), NOT ZONE, unlike the StackFood source. N5 — mart prices per
 * (zone, module), and a zone that frees delivery for Food has said nothing about Grocery. The
 * module half lives in `free_delivery_module` because a setup connects many; see that migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id');
            // See FreeDelivery::TYPE_*. 'all_store', not StackFood's 'all_restaurant' — this
            // platform has stores, and the port doc names the value accordingly.
            $table->string('type', 30)->default('all_store')->comment('all_store | specific_criteria');
            $table->decimal('minimum_order_amount', 10, 2)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            // The lookup the fee pipeline makes on every quote: this zone's active setups.
            $table->index(['zone_id', 'status'], 'free_deliveries_zone_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_deliveries');
    }
};
