<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New Advertisement and Advertisement List are now granted by a single
 * `advertisement_management` permission, offered inside the Marketing card.
 *
 * Any role holding one of the legacy keys gains the merged one. The legacy keys
 * are deliberately KEPT: the Service provider sidebars still call
 * employee_module_permission_check('advertisement'|'advertisement_list')
 * directly, and those live outside the level-scoped registry.
 *
 * Same shape as 2026_08_06_000006_merge_deliveryman_management_permission.
 */
return new class extends Migration
{
    private const MERGED = 'advertisement_management';

    private const COVERED = ['advertisement', 'advertisement_list'];

    public function up(): void
    {
        DB::table('employee_roles')->select('id', 'modules')->orderBy('id')->chunkById(100, function ($roles) {
            foreach ($roles as $role) {
                $modules = (array) json_decode($role->modules ?? '[]', true);

                if (in_array(self::MERGED, $modules)) {
                    continue;
                }

                if (! array_intersect(self::COVERED, $modules)) {
                    continue;
                }

                $modules[] = self::MERGED;

                DB::table('employee_roles')->where('id', $role->id)->update([
                    'modules' => json_encode(array_values($modules)),
                ]);
            }
        });
    }

    public function down(): void
    {
    }
};
