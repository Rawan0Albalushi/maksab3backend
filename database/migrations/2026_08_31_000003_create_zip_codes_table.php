<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ZIP codes — the other half of a zone's named coverage.
 *
 * Keyed on `zone_id` alone for the same reason areas are: geography does not change with the
 * module. See 2026_08_31_000002_create_areas_table.
 *
 * ZIP CODES ARE NOT GLOBALLY UNIQUE (§7). Two zones legitimately share a code where their
 * coverage overlaps, so uniqueness is per zone. A global unique index would reject valid data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zip_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id');
            $table->string('zip_code', 20);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['zone_id', 'status'], 'zip_codes_zone_status_index');
            $table->index('zip_code', 'zip_codes_code_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zip_codes');
    }
};
