<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Weight Rules step of the Delivery Rule wizard — what each weight band ADDS to the base.
 *
 * ADDITIVE, NOT A BASE. `delivery_rule_charges` selects the base (an area or a ZIP prices the
 * order outright); this table stacks on top of whatever the pricing method produced. That is why
 * it is a separate table rather than more nullable columns on `delivery_rule_charges` — folding
 * both roles together makes every read need a "which column is set" discriminator and hides the
 * base-vs-additive distinction that matters at review time (parcel brief §3).
 *
 * NOT BLOCKED ON §1. The open pricing-model question is how a parcel CATEGORY's charge combines
 * with the rule. Weight stacks additively under every candidate model, so this table's shape does
 * not depend on that answer. `delivery_rule_parcel_category_charges` is the one that does, and it
 * is still not created.
 *
 * No `module_id`: these rows inherit scope from their rule, exactly as `delivery_rule_charges`
 * does.
 *
 * The Status toggle lives on `delivery_rules`, not here, because the wizard holds everything until
 * Submit — the toggle is a property of the rule, not of any one band's charge (§6a question 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_rule_weight_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_rule_id');
            $table->foreignId('weight_id');
            $table->decimal('charge', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['delivery_rule_id', 'weight_id'], 'drwc_unique');
        });

        Schema::table('delivery_rules', function (Blueprint $table) {
            // Default false: a rule that started charging by weight the moment the parcel module
            // was connected would surprise every existing parcel order.
            $table->boolean('weight_charge_status')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_rule_weight_charges');

        Schema::table('delivery_rules', function (Blueprint $table) {
            $table->dropColumn('weight_charge_status');
        });
    }
};
