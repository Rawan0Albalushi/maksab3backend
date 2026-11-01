<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Push copy for the happy hour enrolment conversation.
 *
 * There is no "withdrawn" counterpart to BOGO's. A store leaving a happy hour is leaving a rate,
 * and the admin's own list already shows it; a store leaving a BOGO offer takes a built bundle
 * with it, which is worth telling someone about.
 */
return new class extends Migration
{
    private const MODULE_TYPES = ['grocery', 'food', 'pharmacy', 'ecommerce'];

    private const MESSAGES = [
        'happy_hour_join_request' => 'Your request to join the Happy Hour offer {offerTitle} has been submitted and is awaiting review.',
        'happy_hour_join_accepted' => 'Your request to join the Happy Hour offer {offerTitle} has been approved.',
        'happy_hour_join_denied' => 'Your request to join the Happy Hour offer {offerTitle} was declined.',

        'store_happy_hour_invitation' => '{storeName}, you have been invited to join the Happy Hour offer {offerTitle}.',
        'store_happy_hour_join_approval' => '{storeName}, your Happy Hour offer {offerTitle} is now live.',
        'store_happy_hour_join_rejection' => '{storeName}, your Happy Hour offer {offerTitle} enrolment was rejected.',
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
