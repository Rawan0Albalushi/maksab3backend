<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Store Config, Notification Setup, My Shop and Business Plan are now granted by
 * a single `business_section` permission.
 *
 * Any role holding one of the legacy keys gains the merged one. The legacy keys
 * are deliberately KEPT: the Service/Rental provider sidebars still call
 * employee_module_permission_check('store_setup'|'notification_setup'|'my_shop'|
 * 'business_plan') directly, and those live outside the level-scoped registry.
 *
 * Same shape as 2026_08_06_000002_merge_customer_engagement_permission.
 */
return new class extends Migration
{
    private const MERGED = 'business_section';

    private const COVERED = ['store_setup', 'notification_setup', 'my_shop', 'business_plan'];

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
