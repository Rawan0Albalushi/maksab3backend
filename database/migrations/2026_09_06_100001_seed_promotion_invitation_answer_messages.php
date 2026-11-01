<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the admin is told when a store answers an invitation it raised.
 *
 * The 2026_09_02 seeders covered the store-raised half of the conversation -- a store asking to
 * join, and a store walking away -- but not the admin-raised half. When the admin assigns an offer
 * to a store, the store's answer went nowhere: an accepted invitation went live silently and a
 * declined one just sat rejected, so silence in the admin's queue meant either "not answered yet"
 * or "said no last Tuesday". Both are decisions the admin acted on, so both are sent.
 *
 * Same shape and same idempotency as its neighbours: keyed by (key, module_type), written only
 * when the pair is absent, so re-running never duplicates and never overwrites edited copy.
 */
return new class extends Migration
{
    /** The union of the BOGO and happy hour type lists, matching 2026_09_02_100013. */
    private const MODULE_TYPES = ['grocery', 'food', 'pharmacy', 'ecommerce'];

    private const MESSAGES = [
        'admin_bogo_invitation_accepted' => '{storeName} has accepted the BOGO offer {offerTitle} and it is now live.',
        'admin_bogo_invitation_declined' => '{storeName} has declined the BOGO offer {offerTitle}.',
        'admin_happy_hour_invitation_accepted' => '{storeName} has accepted the Happy Hour offer {offerTitle} and it is now live.',
        'admin_happy_hour_invitation_declined' => '{storeName} has declined the Happy Hour offer {offerTitle}.',

        // Not an invitation answer, but the same omission: 2026_09_02_100012 seeded six store-facing
        // happy hour messages where BOGO got seven, so a store leaving a happy hour had no copy of
        // its own while a store leaving a BOGO offer did.
        'store_happy_hour_offer_withdrawn' => '{storeName} has withdrawn from the Happy Hour offer {offerTitle}.',
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

        DB::table('notification_messages')
            ->whereIn('key', array_keys(self::MESSAGES))
            ->whereIn('module_type', self::MODULE_TYPES)
            ->delete();
    }
};
