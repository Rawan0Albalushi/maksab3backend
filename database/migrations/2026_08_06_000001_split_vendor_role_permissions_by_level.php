<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vendor role permissions are now scoped per navigation level (v1 L1 / v2 L2),
 * which splits four nav items off the parent permission they used to share:
 *
 *   Flash Sales          rode on `item`
 *   My Category          rode on `category`
 *   Store Earning Report rode on `expense_report`
 *   Employee Role        checked `role`, which no role form ever granted
 *
 * Each new key is granted to any role that already holds the parent, so no
 * existing employee loses access. Follows the shape of
 * 2026_07_28_000001_backfill_and_consolidate_role_permissions.
 */
return new class extends Migration
{
    /** parent permission => new child permission it now implies */
    private const DERIVED = [
        'item' => 'flash_sale',
        'category' => 'my_category',
        'expense_report' => 'store_earning_report',
        'employee' => 'role',
    ];

    public function up(): void
    {
        DB::table('employee_roles')->select('id', 'modules')->orderBy('id')->chunkById(100, function ($roles) {
            foreach ($roles as $role) {
                $modules = (array) json_decode($role->modules ?? '[]', true);
                $updated = $modules;

                foreach (self::DERIVED as $parent => $child) {
                    if (in_array($parent, $modules) && ! in_array($child, $updated)) {
                        $updated[] = $child;
                    }
                }

                if ($updated !== $modules) {
                    DB::table('employee_roles')->where('id', $role->id)->update([
                        'modules' => json_encode(array_values($updated)),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
    }
};
