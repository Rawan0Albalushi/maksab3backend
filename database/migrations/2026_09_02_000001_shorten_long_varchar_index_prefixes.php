<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuilds the five indexes that covered a varchar(255) column whole.
 *
 * 2026_09_01_100001 originally indexed order_status, order_type and order_transactions.status
 * at full width. Each costs 255*4+2 = 1022 bytes of key under utf8mb4, which pushes those
 * indexes past the 767-byte limit InnoDB enforces for the COMPACT and REDUNDANT row formats --
 * the default on MariaDB 10.1 and older, and on hosts that still set innodb_default_row_format.
 * On a DYNAMIC installation the limit is 3072 and they were created fine, just several times
 * larger than they need to be.
 *
 * That migration now declares prefixes, which fixes fresh installs. It has already run
 * elsewhere though, and a recorded migration is never re-applied, so the servers that took the
 * first version need this one to rebuild what they already have.
 *
 * Idempotent by inspection, not by flag: it reads the prefix length actually stored for each
 * index and leaves anything already correct alone. A fresh install therefore does no work here.
 */
return new class extends Migration
{
    /** Entries may carry a prefix length as name(n); these mirror 2026_09_01_100001. */
    private const INDEXES = [
        'orders' => [
            'orders_status_guest_user_index' => ['order_status(32)', 'is_guest', 'user_id'],
            'orders_status_created_index' => ['order_status(32)', 'created_at'],
            'orders_type_id_index' => ['order_type(32)', 'id'],
            'orders_store_summary_cover_index' => ['store_id', 'order_status(32)', 'order_type(32)', 'refund_requested', 'order_amount'],
        ],
        'order_transactions' => [
            'order_transactions_order_status_index' => ['order_id', 'status(48)'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if ($this->prefixesAlreadyStored($table, $name, $columns)) {
                    continue;
                }

                if ($this->indexExists($table, $name)) {
                    DB::statement('ALTER TABLE `'.$table.'` DROP INDEX `'.$name.'`');
                }

                DB::statement('ALTER TABLE `'.$table.'` ADD INDEX `'.$name.'` ('.$this->columnList($columns).')');

                echo sprintf('  %s.%s rebuilt with prefixes%s', $table, $name, PHP_EOL);
            }
        }
    }

    /**
     * Nothing to undo. Restoring the full-width form would recreate an index that cannot be
     * built at all under COMPACT, and 2026_09_01_100001's own down() already drops these by
     * name whatever their prefix, so a real rollback still works.
     */
    public function down(): void {}

    /** True when every declared prefix is already the stored prefix for that column. */
    private function prefixesAlreadyStored(string $table, string $name, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! preg_match('/^(\w+)\((\d+)\)$/', $column, $matches)) {
                continue;
            }

            // Aliased on purpose: MySQL 8+ returns information_schema columns upper-cased
            // while MariaDB returns them lower-cased, so reading ->sub_part works on one and
            // raises "Undefined property" on the other. An explicit alias comes back as written
            // on both.
            $stored = DB::selectOne(
                'SELECT sub_part AS prefix_length FROM information_schema.statistics
                 WHERE table_schema = ? AND table_name = ? AND index_name = ? AND column_name = ?',
                [DB::getDatabaseName(), $table, $name, $matches[1]]
            );

            // No row means the index is missing entirely; a null prefix means full width.
            if ($stored === null || (int) $stored->prefix_length !== (int) $matches[2]) {
                return false;
            }
        }

        return true;
    }

    /** Column list for an ADD INDEX, where an entry may carry a prefix length as name(n). */
    private function columnList(array $columns): string
    {
        return implode(', ', array_map(function (string $column) {
            return preg_match('/^(\w+)\((\d+)\)$/', $column, $m)
                ? '`'.$m[1].'`('.$m[2].')'
                : '`'.$column.'`';
        }, $columns));
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [DB::getDatabaseName(), $table, $name]
        ) !== null;
    }
};
