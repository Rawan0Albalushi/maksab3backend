<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Dimension Connect" on the Vehicles Category form — the package size classes a vehicle in this
 * category can carry.
 *
 * A pivot rather than a column for the same reason as the express-vehicle filter: the choice is a
 * SET, and both sides are admin-editable rows. The list column shows the connected classes by
 * name ("Small, Medium").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('d_m_vehicle_dimension', function (Blueprint $table) {
            $table->id();
            $table->foreignId('d_m_vehicle_id');
            $table->foreignId('dimension_id');
            $table->timestamps();

            $table->unique(['d_m_vehicle_id', 'dimension_id'], 'dmvd_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('d_m_vehicle_dimension');
    }
};
