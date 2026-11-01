<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Z3, applied to the zones that predate it.
 *
 * A zone may only be switched on once at least one (zone, module) combination can BOTH price a
 * delivery and quote a time for it. The guard has only ever covered the toggle and the status
 * URL, so zones switched on before the rule existed kept their status — including zones that
 * satisfy neither half.
 *
 * This switches those off. It is deliberately the same question `Zone::gapsBetween()` asks, run
 * in SQL: is there a module with an ACTIVE delivery rule and an ACTIVE ETA configuration?
 *
 * ⚠️ This takes zones offline. Run `php artisan zones:readiness-report` first — it prints exactly
 * which zones this will switch off and whether they are currently serving customers.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ready = $this->readyZoneIds();

        $affected = DB::table('zones')
            ->where('status', 1)
            ->when($ready !== [], fn ($query) => $query->whereNotIn('id', $ready))
            ->pluck('name', 'id');

        if ($affected->isEmpty()) {
            return;
        }

        DB::table('zones')->whereIn('id', $affected->keys())->update(['status' => 0]);

        // Written down rather than only logged: down() cannot know which zones it switched off
        // unless up() says so, and "which zones did that migration take offline" is a question
        // someone will ask later.
        DB::table('business_settings')->updateOrInsert(
            ['key' => 'zones_deactivated_by_readiness_migration'],
            ['value' => $affected->keys()->implode(',')],
        );
    }

    /**
     * Reverses only what this migration did.
     *
     * A blanket "switch everything back on" would activate zones that were already off for
     * reasons of their own, so the ids up() recorded are the only ones touched.
     */
    public function down(): void
    {
        $recorded = DB::table('business_settings')
            ->where('key', 'zones_deactivated_by_readiness_migration')
            ->value('value');

        if ($recorded) {
            DB::table('zones')
                ->whereIn('id', array_filter(explode(',', $recorded)))
                ->update(['status' => 1]);
        }

        DB::table('business_settings')->where('key', 'zones_deactivated_by_readiness_migration')->delete();
    }

    /** Zone ids where some module carries an active rule AND an active ETA configuration. */
    private function readyZoneIds(): array
    {
        return DB::table('delivery_rule_module as drm')
            ->join('delivery_rules as dr', 'dr.id', '=', 'drm.delivery_rule_id')
            ->join('eta_configurations as ec', 'ec.zone_id', '=', 'dr.zone_id')
            ->join('eta_configuration_module as ecm', function ($join) {
                $join->on('ecm.eta_configuration_id', '=', 'ec.id')
                    ->on('ecm.module_id', '=', 'drm.module_id');
            })
            ->where('dr.status', 1)
            ->where('ec.status', 1)
            ->distinct()
            ->pluck('dr.zone_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
};
