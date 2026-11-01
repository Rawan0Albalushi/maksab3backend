<?php

use App\Support\Notification\NotificationGate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The customer-audience toggles for the promotion broadcast seeded alongside.
 *
 * Same shape as 2026_09_02_100014: `type` is the audience, `module_type` is 'all' for the core
 * verticals, and the channel columns take 'active' / 'disable' rather than booleans.
 *
 * Push on, mail and SMS off. An offer going live is worth a notification and is not worth an
 * email — the copy has a shelf life measured in hours, and a customer who reads it tomorrow reads
 * about an offer that has moved on.
 *
 * The gate's matrix is cached, so both directions bust it. Without that, a running install keeps
 * answering from a matrix built before these rows existed and the broadcast stays silent until the
 * cache happens to expire — which looks exactly like the feature not working.
 */
return new class extends Migration
{
    private const SETTINGS = [
        [
            'key' => 'customer_bogo_offer',
            'type' => 'customer',
            'title' => 'Customer BOGO Offer',
            'sub_title' => 'Sent to customers in the zone when a BOGO offer goes live at a store',
            'push' => 'active',
            'mail' => 'disable',
        ],
        [
            'key' => 'customer_happy_hour_offer',
            'type' => 'customer',
            'title' => 'Customer Happy Hour Offer',
            'sub_title' => 'Sent to customers in the zone when a Happy Hour offer goes live at a store',
            'push' => 'active',
            'mail' => 'disable',
        ],
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

        NotificationGate::flush();
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

        NotificationGate::flush();
    }
};
