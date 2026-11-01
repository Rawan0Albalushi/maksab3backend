<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The admin's side of both enrolment conversations.
 *
 * The two seeders before this one cover what a *store* is told. These cover what the *admin* is
 * told, and they exist because an enrolment can be opened from either end: a vendor asking to join
 * needs to reach the admin's queue, and a vendor walking away from a live offer needs to reach it
 * too -- otherwise the first anyone notices is a bundle that stopped selling.
 *
 * Seeded across the union of both features' module types, since a single admin panel handles
 * whichever of the two a given module can run.
 */
return new class extends Migration
{
    /** The union of the BOGO and happy hour type lists. */
    private const MODULE_TYPES = ['grocery', 'food', 'pharmacy', 'ecommerce'];

    private const MESSAGES = [
        'admin_bogo_join_request' => '{storeName} has requested to join the BOGO offer {offerTitle}.',
        'admin_bogo_offer_withdrawn' => '{storeName} has withdrawn from the BOGO offer {offerTitle}.',
        'admin_happy_hour_join_request' => '{storeName} has requested to join the Happy Hour offer {offerTitle}.',
        'admin_happy_hour_withdrawn' => '{storeName} has withdrawn from the Happy Hour offer {offerTitle}.',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('notification_messages')) {
            return;
        }

        $now = now();

        foreach (self::MODULE_TYPES as $moduleType) {
            foreach (self::MESSAGES as $key => $message) {
                $exists = DB::table('notification_messages')
                    ->where('key', $key)
                    ->where('module_type', $moduleType)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('notification_messages')->insert([
                    'module_type' => $moduleType,
                    'key' => $key,
                    'message' => $message,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_messages')) {
            return;
        }

        // Scoped by KEY alone, not also by MODULE_TYPES. These keys belong entirely to this
        // feature, so every row carrying one is this migration's to remove -- whereas narrowing
        // the type list later would leave rows seeded under the old list stranded, with no
        // migration that admits to owning them.
        DB::table('notification_messages')
            ->whereIn('key', array_keys(self::MESSAGES))
            ->delete();
    }
};
