<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * S19, applied to the zones the S18 rule switched off.
 *
 * `2026_09_05_000001_deactivate_zones_without_a_complete_module` switched off every active zone
 * that had no module carrying both an active delivery rule and an active ETA configuration. S19
 * has not changed that test — one complete module was, and still is, what a zone needs to be on.
 * What changed is what happens to the OTHER modules: they no longer hold the zone back, they are
 * simply unavailable in it.
 *
 * So this is not a general amnesty. It re-activates only the zones that migration recorded, and
 * only those that now pass the same question it asked. On the install this ships from that is
 * zero rows — none of the three has an ETA configuration yet — and that is the expected result,
 * not a failure: the value here is on installs where an admin has since added one and would
 * otherwise have to find the zone by hand.
 *
 * Zones switched off for reasons of their own are never touched: only the recorded ids are read.
 * Run `php artisan zones:readiness-report` before and after.
 */
return new class extends Migration
{
    private const RECORD_KEY = 'zones_deactivated_by_readiness_migration';

    private const REACTIVATED_KEY = 'zones_reactivated_by_availability_migration';

    public function up(): void
    {
        $deactivated = $this->recordedIds(self::RECORD_KEY);

        if ($deactivated === []) {
            return;
        }

        $eligible = DB::table('zones')
            ->whereIn('id', $deactivated)
            ->where('status', 0)
            ->whereIn('id', $this->completeZoneIds())
            ->pluck('id')
            ->all();

        if ($eligible === []) {
            return;
        }

        DB::table('zones')->whereIn('id', $eligible)->update(['status' => 1]);

        // Recorded for the same reason up() recorded the deactivation: down() cannot otherwise
        // tell a zone this migration switched on from one an admin switched on afterwards.
        DB::table('business_settings')->updateOrInsert(
            ['key' => self::REACTIVATED_KEY],
            ['value' => implode(',', $eligible)],
        );
    }

    public function down(): void
    {
        $reactivated = $this->recordedIds(self::REACTIVATED_KEY);

        if ($reactivated !== []) {
            DB::table('zones')->whereIn('id', $reactivated)->update(['status' => 0]);
        }

        DB::table('business_settings')->where('key', self::REACTIVATED_KEY)->delete();
    }

    /** @return array<int, int> */
    private function recordedIds(string $key): array
    {
        $recorded = DB::table('business_settings')->where('key', $key)->value('value');

        return $recorded
            ? array_values(array_map('intval', array_filter(explode(',', $recorded))))
            : [];
    }

    /**
     * Zone ids where some module carries an active rule AND an active ETA configuration.
     *
     * Byte-for-byte the query the deactivation migration used, so the two cannot disagree about
     * which zones qualify. Deliberately NOT `Zone::gapsBetween()`: a migration that calls into
     * application code answers with whatever that code says on the day it runs, and the point of
     * a migration is to be reproducible.
     *
     * The capability exemption is not applied here either, for the same reason it was not there:
     * it reads `config('module.*')`, which a migration must not depend on. A zone connected only
     * to rental or service is therefore left off for an admin to switch on deliberately — the
     * conservative direction, and the one the earlier migration already chose.
     *
     * @return array<int, int>
     */
    private function completeZoneIds(): array
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
