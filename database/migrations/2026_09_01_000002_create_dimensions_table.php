<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Package size classes — "Small", "Medium" — with the largest box each accepts. The parcel tier's
 * Dimension Setup screen.
 *
 * Scope note: CRUD settings only. `delivery_rule_dimension_charges` and the `d_m_vehicles`
 * extension (delivery-zone-suite-parcel-deferred.md §3) are NOT created here — they belong to the
 * deferred pricing work.
 *
 * GLOBAL, NOT ZONE-SCOPED, for the same reason as `weights`: the class describes the package, and
 * the charge that varies by geography lives on the delivery rule.
 *
 * INCHES ARE A SETUP VALUE (port doc §3.2) — never converted through
 * `business_settings.distance_unit`, which governs distance only. The design prints "in" as fixed
 * text beside every measurement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dimensions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('max_length', 8, 2);
            $table->decimal('max_width', 8, 2);
            $table->decimal('max_height', 8, 2);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('status', 'dimensions_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dimensions');
    }
};
