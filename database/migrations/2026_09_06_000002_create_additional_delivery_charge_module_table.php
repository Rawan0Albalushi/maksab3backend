<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The modules one Additional Delivery Charge setup covers.
 *
 * A pivot for the same reason delivery rules, free delivery and ETA use one. The design makes
 * Module a multi-select and its side note allows one setup per zone AND module, which together
 * make uniqueness an OVERLAP question — "does any existing setup in this zone already claim any
 * of these modules?" — which `unique(zone_id, module_id)` on the parent cannot express.
 *
 * This is the fourth feature on that shape, not a fourth invention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_delivery_charge_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('additional_delivery_charge_id');
            $table->foreignId('module_id');
            $table->timestamps();

            $table->unique(['additional_delivery_charge_id', 'module_id'], 'adcm_unique');
            // The lookup a quote makes: this module's setup within a zone.
            $table->index(['module_id', 'additional_delivery_charge_id'], 'adcm_module_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_delivery_charge_module');
    }
};
