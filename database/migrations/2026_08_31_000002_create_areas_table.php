<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Areas — named coverage inside a zone.
 *
 * KEYED ON `zone_id` ALONE, deliberately (delivery-zone-suite-port.md §0.1). Every other table
 * this port adds is keyed `(zone_id, module_id)`; areas and ZIP codes are the documented
 * exception, because they are GEOGRAPHY. "Gulshan-1" does not become a different place when a
 * customer switches from grocery to pharmacy, and duplicating the list per module would make the
 * admin maintain the same streets four times. The *charges* against them inherit module scope
 * from their parent delivery rule. Do not "fix" this by adding module_id.
 *
 * ROLE MATRIX: no new entry. The screen lives inside the existing `zone` route group, which is
 * already gated by `module:settings` — the same permission the zone screens themselves use, per
 * the `'zone' => ['settings']` remap in 2026_07_28_000001_backfill_and_consolidate_role_permissions.
 * Adding a key here would create a permission nothing checks. (§13.1's requirement is that the
 * screens are reachable by the right roles, which inheritance already satisfies.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id');
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['zone_id', 'status'], 'areas_zone_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
