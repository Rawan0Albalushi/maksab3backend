<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which area and ZIP code a deliveryman belongs to.
 *
 * Nullable and unconstrained. Most orders are priced by distance or a fixed amount and never
 * carry either (§6), so these columns are usually empty and must not be required.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delivery_men')) {
            return;
        }

        Schema::table('delivery_men', function (Blueprint $table) {
            if (! Schema::hasColumn('delivery_men', 'area_id')) {
                $table->unsignedBigInteger('area_id')->nullable()->index();
            }
            if (! Schema::hasColumn('delivery_men', 'zip_code_id')) {
                $table->unsignedBigInteger('zip_code_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('delivery_men')) {
            return;
        }

        Schema::table('delivery_men', function (Blueprint $table) {
            foreach (['area_id', 'zip_code_id'] as $column) {
                if (Schema::hasColumn('delivery_men', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
