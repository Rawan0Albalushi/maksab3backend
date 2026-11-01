<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Dimension Rules step of the Delivery Rule wizard — what each size class ADDS to the base.
 *
 * Same reasoning as `delivery_rule_weight_charges`: additive rather than a base, its own table
 * rather than more nullable columns, scope inherited from the rule, and independent of the §1
 * pricing-model decision.
 *
 * Separate migration from weight so the two can be applied and rolled back independently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_rule_dimension_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_rule_id');
            $table->foreignId('dimension_id');
            $table->decimal('charge', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['delivery_rule_id', 'dimension_id'], 'drdc_unique');
        });

        Schema::table('delivery_rules', function (Blueprint $table) {
            $table->boolean('dimension_charge_status')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_rule_dimension_charges');

        Schema::table('delivery_rules', function (Blueprint $table) {
            $table->dropColumn('dimension_charge_status');
        });
    }
};
