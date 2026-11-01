<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which area and ZIP code a order belongs to.
 *
 * Nullable and unconstrained. Most orders are priced by distance or a fixed amount and never
 * carry either (§6), so these columns are usually empty and must not be required.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'area_id')) {
                $table->unsignedBigInteger('area_id')->nullable()->index();
            }
            if (! Schema::hasColumn('orders', 'zip_code_id')) {
                $table->unsignedBigInteger('zip_code_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach (['area_id', 'zip_code_id'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
