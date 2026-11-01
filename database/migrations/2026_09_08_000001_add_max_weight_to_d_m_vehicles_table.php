<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The heaviest load a vehicle in this category is trusted with.
 *
 * From the Vehicles Category design: the list prints it as "1200 kg" and the form marks it
 * required. Stored in whatever `weight_unit` names, exactly as typed — a SETUP value, never
 * converted, the same treatment `starting_coverage_area` gets for distance.
 *
 * NULLABLE in the database even though the form requires it: rows created before this screen
 * existed have no value to backfill, and inventing one would put a number on the list that no
 * admin ever chose. The form's `required` rule is what stops new blanks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('d_m_vehicles', function (Blueprint $table) {
            $table->decimal('max_weight', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('d_m_vehicles', function (Blueprint $table) {
            $table->dropColumn('max_weight');
        });
    }
};
