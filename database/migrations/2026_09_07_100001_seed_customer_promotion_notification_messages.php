<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer-facing copy for a promotion going live.
 *
 * The seven BOGO keys seeded by 2026_09_02_100011 all speak to a vendor about an enrolment. A
 * customer has no stake in an enrolment: they have one in a bundle they can now buy. So this is
 * one key per promotion, not seven — the customer sees a single event where the vendor sees a
 * conversation.
 *
 * Keyed per module_type like every other row in this table, and only for the types that can run
 * these promotions. Seeding a type that cannot would put a toggle in a panel that can never fire.
 *
 * Idempotent by inspection: a row is written only when that key/module_type pair is absent, so
 * re-running never duplicates and never overwrites copy an operator has since edited.
 */
return new class extends Migration
{
    private const MODULE_TYPES = ['grocery', 'food', 'pharmacy', 'ecommerce'];

    private const MESSAGES = [
        'customer_bogo_offer_live' => '{storeName} is running the BOGO offer {offerTitle}. Order now while it lasts.',
        'customer_happy_hour_offer_live' => '{storeName} has started the Happy Hour offer {offerTitle}. Order now while it lasts.',
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
