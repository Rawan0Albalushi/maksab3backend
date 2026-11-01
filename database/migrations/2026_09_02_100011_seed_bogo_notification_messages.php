<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Push copy for the BOGO enrolment conversation.
 *
 * Not a copy of the source's seeder. StackFood has one row per message because it has one
 * vertical; 6amMart's notification_messages is keyed by module_type, so every message is seeded
 * once per type that can run the feature. A row for a type that cannot would show a toggle in a
 * panel that can never fire.
 *
 * Both directions of the conversation live here. An admin invitation and a vendor request are the
 * same pending row read by opposite audiences, so they need different copy: one asks the vendor to
 * decide, the other tells the vendor the admin is deciding.
 *
 * Idempotent by inspection: a row is written only when that key/module_type pair is absent, so
 * re-running never duplicates and never overwrites copy an operator has since edited.
 */
return new class extends Migration
{
    private const MODULE_TYPES = ['grocery', 'food', 'pharmacy', 'ecommerce'];

    private const MESSAGES = [
        // Vendor-facing: the outcome of a request this store made.
        'bogo_join_request' => 'Your request to join the BOGO offer {offerTitle} has been submitted and is awaiting review.',
        'bogo_join_accepted' => 'Your request to join the BOGO offer {offerTitle} has been approved.',
        'bogo_join_denied' => 'Your request to join the BOGO offer {offerTitle} was declined.',

        // Store-facing: something the admin did to this store's enrolment.
        'store_bogo_invitation' => '{storeName}, you have been invited to join the BOGO offer {offerTitle}.',
        'store_bogo_join_approval' => '{storeName}, your BOGO offer {offerTitle} is now live.',
        'store_bogo_join_rejection' => '{storeName}, your BOGO offer {offerTitle} enrolment was rejected.',
        'store_bogo_offer_withdrawn' => '{storeName} has withdrawn from the BOGO offer {offerTitle}.',
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
