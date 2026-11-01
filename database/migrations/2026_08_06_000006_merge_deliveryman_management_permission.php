<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add Delivery Man and Deliverymen List are now granted by a single
 * `deliveryman_management` permission.
 *
 * Any role holding one of the legacy keys gains the merged one. The legacy keys
 * are deliberately KEPT: layouts/admin/partials/_sidebar_users.blade.php and
 * _sidebar_v2_users.blade.php still call
 * employee_module_permission_check('deliveryman') directly.
 *
 * Note the store's self_delivery_system flag used to ride on that helper call;
 * it is now an explicit `visible` condition on both units in
 * App\Navigation\VendorNav, so merging the grant does not widen access.
 *
 * Same shape as 2026_08_06_000005_merge_wallet_management_permission.
 */
return new class extends Migration
{
    private const MERGED = 'deliveryman_management';

    private const COVERED = ['deliveryman', 'deliveryman_list'];

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
