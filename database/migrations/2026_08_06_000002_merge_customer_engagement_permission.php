<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reviews and Chat are now granted by a single `customer_engagement` permission.
 *
 * Any role holding either legacy key gains the merged one. The legacy keys are
 * deliberately KEPT: the vendor header chat badge, layouts/vendor/app.blade.php
 * and the Service/Rental provider sidebars still call
 * employee_module_permission_check('chat'|'reviews') directly, and those live
 * outside the level-scoped registry.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('employee_roles')->select('id', 'modules')->orderBy('id')->chunkById(100, function ($roles) {
            foreach ($roles as $role) {
                $modules = (array) json_decode($role->modules ?? '[]', true);

                if (in_array('customer_engagement', $modules)) {
                    continue;
                }

                if (! array_intersect(['reviews', 'chat'], $modules)) {
                    continue;
                }

                $modules[] = 'customer_engagement';

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
