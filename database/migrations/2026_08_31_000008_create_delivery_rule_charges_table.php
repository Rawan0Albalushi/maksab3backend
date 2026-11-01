<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-area and per-ZIP amounts an area_wise or zip_code_wise delivery rule charges.
 *
 * No `module_id`: a charge inherits its scope from the parent rule, which is already keyed
 * `(zone_id, module_id)`. Exactly one of `area_id` / `zip_code_id` is set on any row — which one
 * follows from the parent rule's `pricing_method`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_rule_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_rule_id');
            $table->foreignId('area_id')->nullable();
            $table->foreignId('zip_code_id')->nullable();
            $table->decimal('charge', 10, 2)->default(0);
            $table->timestamps();

            $table->index('delivery_rule_id', 'drc_rule_index');
            $table->index(['delivery_rule_id', 'area_id'], 'drc_area_index');
            $table->index(['delivery_rule_id', 'zip_code_id'], 'drc_zip_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_rule_charges');
    }
};
