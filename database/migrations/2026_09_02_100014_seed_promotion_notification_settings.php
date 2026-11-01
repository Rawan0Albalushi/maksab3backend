<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The per-channel toggles that decide whether the copy seeded alongside is actually sent.
 *
 * notification_settings does NOT follow notification_messages' convention, and the difference is
 * easy to miss: messages are keyed per concrete module_type, while settings use module_type 'all'
 * for the core verticals and only rental and service carry their own rows. Seeding these per type
 * would produce rows the settings screen never reads.
 *
 * type is the audience -- 'admin' or 'store' here. The three channel columns take the strings
 * 'active' / 'disable', not booleans.
 *
 * Push defaults on and mail defaults off for the store rows: an enrolment decision is time-
 * sensitive and belongs in the app, while an email for every join request would be noise. SMS is
 * off everywhere because it costs money per message and nobody has asked for it on a promotion.
 *
 * store_notification_settings is deliberately NOT seeded here. Those rows are per store and the
 * application creates them as stores are onboarded -- only 39 of 74 stores currently have any --
 * so backfilling them from a migration would invent state the app is responsible for.
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['key' => 'bogo_offer_enrollment', 'type' => 'store', 'title' => 'BOGO Offer Enrollment', 'sub_title' => 'Sent when a BOGO offer enrollment is invited, approved or rejected', 'push' => 'active', 'mail' => 'disable'],
        ['key' => 'happy_hour_enrollment', 'type' => 'store', 'title' => 'Happy Hour Enrollment', 'sub_title' => 'Sent when a Happy Hour enrollment is invited, approved or rejected', 'push' => 'active', 'mail' => 'disable'],
        ['key' => 'bogo_offer_request', 'type' => 'admin', 'title' => 'BOGO Offer Request', 'sub_title' => 'Sent when a store requests to join or withdraws from a BOGO offer', 'push' => 'active', 'mail' => 'disable'],
        ['key' => 'happy_hour_request', 'type' => 'admin', 'title' => 'Happy Hour Request', 'sub_title' => 'Sent when a store requests to join or withdraws from a Happy Hour offer', 'push' => 'active', 'mail' => 'disable'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('notification_settings')) {
            return;
        }

        $now = now();

        foreach (self::SETTINGS as $setting) {
            $exists = DB::table('notification_settings')
                ->where('key', $setting['key'])
                ->where('type', $setting['type'])
                ->where('module_type', 'all')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('notification_settings')->insert([
                'title' => $setting['title'],
                'sub_title' => $setting['sub_title'],
                'key' => $setting['key'],
                'type' => $setting['type'],
                'module_type' => 'all',
                'mail_status' => $setting['mail'],
                'sms_status' => 'disable',
                'push_notification_status' => $setting['push'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_settings')) {
            return;
        }

        DB::table('notification_settings')
            ->whereIn('key', array_column(self::SETTINGS, 'key'))
            ->where('module_type', 'all')
            ->delete();
    }
};
