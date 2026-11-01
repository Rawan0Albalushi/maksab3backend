<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds `business_settings.distance_unit`.
 *
 * StackFood shipped this setting without seeding it. Because the admin form marks the field
 * required, the first save of ANY unrelated setting on that page then failed validation until
 * somebody noticed the new dropdown. Cheap to avoid, so it is avoided here.
 *
 * The row is also added to installation/backup/database*.sql so a fresh install starts with it
 * rather than relying on this migration having run.
 *
 * Existing installs get `km`, which is what every stored distance already means — seeding `mi`
 * would silently reinterpret every rate and every coverage band on the platform (§3.5, M6).
 */
return new class extends Migration
{
    private const KEY = 'distance_unit';

    private const DEFAULT = 'km';

    public function up(): void
    {
        if (DB::table('business_settings')->where('key', self::KEY)->exists()) {
            return;
        }

        DB::table('business_settings')->insert([
            'key' => self::KEY,
            'value' => self::DEFAULT,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('business_settings')->where('key', self::KEY)->delete();
    }
};
