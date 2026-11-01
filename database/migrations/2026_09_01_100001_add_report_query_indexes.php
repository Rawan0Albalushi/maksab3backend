<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the admin reports and the store/item list endpoints, on core tables only.
 *
 * Tables owned by a module are indexed from that module's own migrations instead: an app-level
 * migration guarded with hasTable() would be recorded as run while the module is disabled, and
 * would then never create its indexes once the module was enabled.
 *
 * Measured, worst case first:
 *   orders (order_status, created_at)                  vendor tax report   32.92s -> 1.08s
 *   orders (order_type, id) + order_transactions       store earning txns  45.61s -> 2.83s
 *     (order_id, status)                               admin earning txns  27.32s -> 4.22s
 *   orders (store_id, order_status, ...)               store-wise report   34.52s -> 7.63s
 *   store_schedule (store_id, day)                     "is store open"      3.61s -> 0.031s
 *   order_transactions created_at variants             date-filtered reports scanned all rows
 *   orders (order_status, is_guest, user_id)           dashboard top customers
 *
 * order_type is not selective (nearly every order is 'delivery'); its index makes scanning and
 * probing cheaper rather than narrowing the row count. The orders covering index took ~5.5
 * minutes to build over 2M rows here -- budget for it.
 *
 * order_status, order_type and order_transactions.status are varchar(255). Indexed whole, each
 * costs 255*4+2 = 1022 bytes of key, putting four of these indexes past the 767-byte limit that
 * InnoDB still enforces under the COMPACT row format -- the default on MariaDB 10.1 and older,
 * and on hosts that set innodb_default_row_format. They are indexed by prefix instead: the
 * longest value any of the three holds is 32 characters ('refunded_without_delivery_charge'),
 * so the prefixes below are lossless for every value in use and leave room for more.
 */
return new class extends Migration
{
    private const INDEXES = [
        'store_schedule' => [
            'store_schedule_store_id_day_index' => ['store_id', 'day'],
        ],
        'order_transactions' => [
            'order_transactions_created_at_index' => ['created_at'],
            'order_transactions_zone_created_index' => ['zone_id', 'created_at'],
            'order_transactions_module_created_index' => ['module_id', 'created_at'],
            'order_transactions_order_status_index' => ['order_id', 'status(48)'],
        ],
        'orders' => [
            'orders_status_guest_user_index' => ['order_status(32)', 'is_guest', 'user_id'],
            'orders_status_created_index' => ['order_status(32)', 'created_at'],
            'orders_type_id_index' => ['order_type(32)', 'id'],
            'orders_store_summary_cover_index' => ['store_id', 'order_status(32)', 'order_type(32)', 'refund_requested', 'order_amount'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if ($this->indexExists($table, $name)) {
                    continue;
                }

                // Raw, not Blueprint::index(): the builder has no way to express a prefix
                // length. Both MySQL and MariaDB accept this form.
                DB::statement('ALTER TABLE `'.$table.'` ADD INDEX `'.$name.'` ('.self::columnList($columns).')');
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                if (! $this->indexExists($table, $name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($name) {
                    $blueprint->dropIndex($name);
                });
            }
        }
    }

    /** Column list for an ADD INDEX, where an entry may carry a prefix length as name(n). */
    public static function columnList(array $columns): string
    {
        return implode(', ', array_map(function (string $column) {
            return preg_match('/^(\w+)\((\d+)\)$/', $column, $m)
                ? '`'.$m[1].'`('.$m[2].')'
                : '`'.$column.'`';
        }, $columns));
    }

    /**
     * selectOne rather than DB::table('information_schema.statistics'): the query builder
     * prepends the configured table prefix, so a non-empty DB_PREFIX would send this looking
     * for the wrong table, report every index as missing, and re-run CREATE INDEX.
     */
    private function indexExists(string $table, string $name): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [DB::getDatabaseName(), $table, $name]
        ) !== null;
    }
};
