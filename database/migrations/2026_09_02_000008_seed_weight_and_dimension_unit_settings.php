<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Product decision 4 (2026-09-02) — units centralised: weight, dimension AND distance.
 *
 * `distance_unit` was seeded in S2. These are its two siblings.
 *
 * SEEDED WITH WHAT THE SCREENS ALREADY SAY, not with a preference: weight classes have been
 * captured in kilograms and dimension classes in inches since S4a, and the stored numbers are
 * SETUP values — nothing converts them. Seeding `cm` here would silently reinterpret every
 * dimension class an admin has entered, which is precisely the mistake the distance switch
 * guard exists to prevent.
 *
 * Seeded rather than left absent for the reason §3.1 gives: the settings form marks the field
 * required, so an absent row makes the first save of any unrelated setting on that page fail.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['weight_unit' => 'kg', 'dimension_unit' => 'in'] as $key => $value) {
            DB::table('business_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('business_settings')->whereIn('key', ['weight_unit', 'dimension_unit'])->delete();
    }
};
