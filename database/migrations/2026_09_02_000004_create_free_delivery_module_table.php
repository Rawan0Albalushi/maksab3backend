<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The modules one free-delivery setup covers.
 *
 * A pivot rather than a `module_id` column on the parent: the design's F2 makes Module a
 * multi-select and F1 allows one setup per zone+module combination, which together make
 * uniqueness an OVERLAP question — "does any existing setup in this zone already claim any of
 * these modules?" — not something a `unique(zone_id, module_id)` index can express. Delivery
 * rules settled this shape first; free delivery follows it rather than inventing a second one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_delivery_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('free_delivery_id');
            $table->foreignId('module_id');
            $table->timestamps();

            $table->unique(['free_delivery_id', 'module_id'], 'fdm_unique');
            // "which setup frees this module?" — module first, as the delivery-rule pivot does.
            $table->index(['module_id', 'free_delivery_id'], 'fdm_module_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_delivery_module');
    }
};
