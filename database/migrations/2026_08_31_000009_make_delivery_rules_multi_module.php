<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A delivery rule connects to MANY modules, not one.
 *
 * The design's "Choose Module To Connect" is a multi-select rendering chips, the rule detail lists
 * several module badges, and the list column reads "2 Module" / "4 Module" with a tooltip naming
 * them. My first schema had a single `module_id` foreign key, which cannot express that.
 *
 * WHY A PIVOT AND NOT A `module_ids` JSON COLUMN.
 * `surge_prices` already uses json + whereJsonContains, and delivery-zone-suite-port.md §0.2 says
 * to keep that — but it says so about a column that already ships and whose rows already exist,
 * not as a pattern to copy. A pivot is the better fit here for one concrete reason: the design's
 * rule D1 is *"only one Delivery Rule per Zone & Module combination"*, so the uniqueness question
 * is "does any existing rule in this zone already claim any of these modules?". That is a join
 * against an indexed pivot, not a scan of json blobs. Surge keeps its json; this does not copy it.
 *
 * `delivery_rules.module_id` is kept for one release as the fallback that keeps a half-migrated
 * install serving — the same treatment §4.3 gives the module_zone pivot columns. Existing rows
 * are backfilled into the pivot below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_rule_module', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_rule_id');
            $table->foreignId('module_id');
            $table->timestamps();

            $table->unique(['delivery_rule_id', 'module_id'], 'drm_unique');
            // The lookup that matters: "which rule prices this module?" — module first.
            $table->index(['module_id', 'delivery_rule_id'], 'drm_module_index');
        });

        // Backfill: every rule created before this migration claimed exactly one module.
        $rows = DB::table('delivery_rules')
            ->whereNotNull('module_id')
            ->get(['id', 'module_id'])
            ->map(fn ($rule) => [
                'delivery_rule_id' => $rule->id,
                'module_id' => $rule->module_id,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($rows) {
            DB::table('delivery_rule_module')->insert($rows);
        }

        Schema::table('delivery_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_rule_module');
    }
};
