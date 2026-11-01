<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Moves the saver options off `module_zone_delivery_options` and onto the new setup tables.
 *
 * The old table held one row per (module, zone, delivery_type). A setup here covers a SET of
 * modules, so the two express/slightly_delay rows for one (zone, module) fold into a single
 * setup row with one module attached. Two modules that were configured separately therefore
 * become two setups, which is the truthful reading — nothing in the old table recorded that
 * they had been configured together.
 *
 * `standard` rows carry no charge and no time by definition; they are the absence of an offer,
 * so they are skipped rather than turned into a setup with nothing in it.
 *
 * The old table is LEFT IN PLACE and its rows are left untouched. Nothing reads it after this
 * migration — ModuleZoneDeliveryOptionService now answers from the new tables — but leaving it
 * means `down()` restores the previous behaviour by dropping the new rows alone, with no risk of
 * having thrown away the only copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('module_zone_delivery_options')) {
            return;
        }

        $rows = DB::table('module_zone_delivery_options')
            ->whereIn('delivery_type', ['express', 'slightly_delay'])
            ->get();

        // Group into one setup per (zone, module) — the pair the design says is unique.
        $setups = [];
        foreach ($rows as $row) {
            $key = $row->zone_id.':'.$row->module_id;
            $setups[$key] ??= ['zone_id' => $row->zone_id, 'module_id' => $row->module_id];

            if ($row->delivery_type === 'express') {
                $setups[$key]['express_extra_charge'] = $row->extra_charge;
                $setups[$key]['express_reduce_delivery_time'] = $row->reduce_delivery_time;
            } else {
                $setups[$key]['delay_reduce_charge'] = $row->reduce_charge;
                $setups[$key]['delay_add_delivery_time'] = $row->add_delivery_time;
            }
        }

        foreach ($setups as $setup) {
            // Idempotent: re-running must not create a second setup for the same pair.
            $exists = DB::table('additional_delivery_charges as adc')
                ->join('additional_delivery_charge_module as m', 'm.additional_delivery_charge_id', '=', 'adc.id')
                ->where('adc.zone_id', $setup['zone_id'])
                ->where('m.module_id', $setup['module_id'])
                ->exists();

            if ($exists) {
                continue;
            }

            $id = DB::table('additional_delivery_charges')->insertGetId([
                'zone_id' => $setup['zone_id'],
                'express_extra_charge' => $setup['express_extra_charge'] ?? null,
                'express_reduce_delivery_time' => $setup['express_reduce_delivery_time'] ?? null,
                'delay_reduce_charge' => $setup['delay_reduce_charge'] ?? null,
                'delay_add_delivery_time' => $setup['delay_add_delivery_time'] ?? null,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('additional_delivery_charge_module')->insert([
                'additional_delivery_charge_id' => $id,
                'module_id' => $setup['module_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The old rows were never deleted, so undoing is simply removing what this created.
        DB::table('additional_delivery_charge_vehicle')->truncate();
        DB::table('additional_delivery_charge_module')->truncate();
        DB::table('additional_delivery_charges')->truncate();
    }
};
