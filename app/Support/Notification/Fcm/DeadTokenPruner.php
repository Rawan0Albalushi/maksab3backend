<?php

namespace App\Support\Notification\Fcm;

use App\Support\Notification\NotificationConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DeadTokenPruner
{
    public static function prune(?string $token): int
    {
        if (! config('notification.fcm.prune_dead_tokens', true) || ! FcmTokenResolver::isUsable($token)) {
            return 0;
        }

        $cleared = 0;

        foreach ((array) config('notification.token_columns', []) as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $cleared += DB::table($table)->where($column, $token)->update([$column => null]);
        }

        if ($cleared > 0) {
            Log::channel(NotificationConfig::logChannel())->info('fcm.token_pruned', [
                'rows' => $cleared,
            ]);
        }

        return $cleared;
    }
}
