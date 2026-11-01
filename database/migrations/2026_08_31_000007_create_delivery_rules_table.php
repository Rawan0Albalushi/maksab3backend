<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery rules — the core of the port (delivery-zone-suite-port.md §5.1).
 *
 * ONE ACTIVE RULE PER (zone, module) decides how that combination's delivery charge is
 * calculated. Keyed `(zone_id, module_id)`, unlike areas and ZIP codes: pricing is exactly the
 * thing that DOES change when a customer switches from grocery to pharmacy (§0.1).
 *
 * The invariant is enforced in the model's `saved` hook, not here and not in the controller —
 * three separate paths can switch a rule on (the status endpoint, a store, an update that moved
 * the rule to another zone or module) and a controller-level check is bypassable by the other
 * two (§5.2).
 *
 * SCOPE NOTE — this ships the FOUR BASE PRICING METHODS only. The weight and dimension charge
 * tiers the designs add to the rule detail arrive as their own additive tables hanging off
 * `delivery_rule_id`; nothing here forecloses them.
 *
 * ROLE MATRIX: no new entry, same reasoning as areas and ZIP codes — the screen lives inside the
 * existing zone route group, already gated by `module:settings`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id');
            $table->foreignId('module_id');
            $table->string('name');

            // The floor every method ends at, and the floor every post-engine discount must
            // respect (§5.3, M7).
            $table->decimal('minimum_delivery_charge', 10, 2)->default(0);

            $table->string('pricing_method', 20)->default('fixed_amount')
                ->comment('area_wise | zip_code_wise | distance_wise | fixed_amount');

            // distance_wise. `per_km_charge` is a SETUP value (§3.2) — stored exactly as typed
            // and read in whatever unit business_settings.distance_unit names. Never converted.
            $table->decimal('per_km_charge', 10, 2)->nullable();
            $table->decimal('maximum_delivery_charge', 10, 2)->nullable();

            // fixed_amount.
            $table->decimal('fixed_charge', 10, 2)->nullable();

            // Created switched OFF. A rule that priced orders the moment it was saved, before
            // its area charges were filled in, would quote zero for every area.
            $table->boolean('status')->default(false);
            $table->timestamps();

            $table->index(['zone_id', 'module_id', 'status'], 'delivery_rules_zone_module_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_rules');
    }
};
