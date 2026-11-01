<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The modules one ETA configuration covers.
 *
 * A pivot for the same reason free delivery uses one: E2 makes Module a multi-select and E1
 * allows one configuration per zone+module, which together make uniqueness an OVERLAP question.
 * This is the third feature on that shape, not a third invention.
 *
 * StackFood took a different route for the same pressure — it dropped its unique index and allows
 * several configurations per zone with exactly one active. That works where a zone has one
 * configuration; it cannot express "one per zone AND module" with a multi-select, which is what
 * mart's designs ask for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eta_configuration_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eta_configuration_id');
            $table->foreignId('module_id');
            $table->timestamps();

            $table->unique(['eta_configuration_id', 'module_id'], 'ecm_unique');
            $table->index(['module_id', 'eta_configuration_id'], 'ecm_module_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eta_configuration_module');
    }
};
