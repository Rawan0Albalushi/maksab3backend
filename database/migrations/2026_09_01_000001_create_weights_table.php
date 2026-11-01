<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weight classifications — "0 - 2KG", "2 - 4KG" — the parcel tier's Weight Setup screen.
 *
 * Scope note: this ships the CRUD settings only. The table that prices against these bands,
 * `delivery_rule_weight_charges` (delivery-zone-suite-parcel-deferred.md §3), is NOT created here.
 * It belongs to the deferred pricing work, still blocked on §1 — creating it now would ship an
 * empty table nothing reads.
 *
 * GLOBAL, NOT ZONE-SCOPED. The design carries no zone selector, and the delivery-rule wizard lists
 * every band regardless of the rule's zone. A weight class describes the package; the part that
 * varies by geography is the CHARGE, which lives on the rule. Same reasoning that keeps `areas`
 * keyed on zone alone and the module scope on the rule.
 *
 * KILOGRAMS ARE A SETUP VALUE (port doc §3.2). `from_weight` / `to_weight` are never re-read
 * through `business_settings.distance_unit`, which governs DISTANCE and nothing else. The design
 * labels the column "(KG)" as fixed text for exactly this reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weights', function (Blueprint $table) {
            $table->id();
            // Translated through the shared `translations` table via HasTranslationsTrait, the
            // same way areas and zip codes are — no per-locale columns here.
            $table->string('name');
            $table->decimal('from_weight', 8, 2);
            $table->decimal('to_weight', 8, 2);
            $table->boolean('status')->default(true);
            $table->timestamps();

            // The wizard lists active bands in ascending order; both columns of that read.
            $table->index(['status', 'from_weight'], 'weights_status_from_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weights');
    }
};
