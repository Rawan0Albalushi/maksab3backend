<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * items.top_category_id and items.zone_id -- two derived columns, stored so they can be
 * indexed and sorted on.
 *
 * top_category_id is the top-level category an item rolls up to, from items.category_id and
 * categories.parent_id. It replaces a JSON_CONTAINS predicate over the category_ids varchar,
 * which no index could serve.
 *
 * zone_id is the zone of the store selling the item. Expressed as whereHas('store', ...) it
 * made MySQL drive from the store table: 33.5M rows examined to return ten items.
 *
 * Both are backfilled here, not by a separate command. The queries that read them have no
 * fallback and NULL matches nothing, so migrating without the backfill returns empty results
 * from four customer-facing endpoints while the admin panel looks healthy. Measured at ~29
 * seconds for 1M items.
 *
 * Kept current afterwards by ItemObserver on write, CategoryObserver on re-parenting and
 * StoreObserver when a store changes zone.
 */
return new class extends Migration
{
    private const CHUNK = 50000;

    /** The top-level category rule, as SQL over a joined `c`. Inlined so this has no dependency on app code. */
    private const TOP_CATEGORY_SQL = 'IF(c.parent_id = 0, c.id, c.parent_id)';

    private const INDEXES = [
        'items_top_category_created_index' => ['top_category_id', 'created_at'],
        'items_zone_module_status_created_index' => ['zone_id', 'module_id', 'status', 'is_approved', 'created_at'],
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('items', 'top_category_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->unsignedBigInteger('top_category_id')->nullable();
            });
        }

        if (! Schema::hasColumn('items', 'zone_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->unsignedBigInteger('zone_id')->nullable();
            });
        }

        // Before the indexes, so the backfill does not maintain an index entry per row.
        $this->backfill();

        foreach (self::INDEXES as $name => $columns) {
            if ($this->indexExists('items', $name)) {
                continue;
            }

            Schema::table('items', function (Blueprint $table) use ($columns, $name) {
                $table->index($columns, $name);
            });
        }
    }

    /**
     * Windowed over ids that exist: MySQL rejects LIMIT on a multi-table UPDATE, and
     * fixed-width windows waste iterations where ids are sparse. Only wrong rows are touched,
     * so a re-run costs reads and no writes. An item with no store, or a store with no zone,
     * is left NULL.
     */
    private function backfill(): void
    {
        $started = microtime(true);
        $written = 0;
        $lastId = 0;

        do {
            $ids = DB::table('items')->where('id', '>', $lastId)
                ->orderBy('id')->limit(self::CHUNK)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $windowStart = $ids->first();
            $lastId = $ids->last();

            $written += DB::update('
                UPDATE items i
                JOIN categories c ON c.id = i.category_id
                SET i.top_category_id = '.self::TOP_CATEGORY_SQL.'
                WHERE i.id BETWEEN ? AND ?
                  AND (i.top_category_id IS NULL
                       OR i.top_category_id <> '.self::TOP_CATEGORY_SQL.')', [$windowStart, $lastId]);

            $written += DB::update('
                UPDATE items i
                JOIN stores s ON s.id = i.store_id
                SET i.zone_id = s.zone_id
                WHERE i.id BETWEEN ? AND ?
                  AND (i.zone_id IS NULL OR i.zone_id <> s.zone_id)', [$windowStart, $lastId]);
        } while (true);

        // An item whose category was deleted has no top level to roll up to.
        $cleared = DB::update('
            UPDATE items i
            LEFT JOIN categories c ON c.id = i.category_id
            SET i.top_category_id = NULL
            WHERE c.id IS NULL AND i.top_category_id IS NOT NULL');

        if ($written > 0 || $cleared > 0) {
            echo sprintf('  items derived columns: %s backfilled, %s cleared in %.1fs%s',
                number_format($written), number_format($cleared), microtime(true) - $started, PHP_EOL);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            if ($this->indexExists('items', $name)) {
                Schema::table('items', function (Blueprint $table) use ($name) {
                    $table->dropIndex($name);
                });
            }
        }

        foreach (['top_category_id', 'zone_id'] as $column) {
            if (Schema::hasColumn('items', $column)) {
                Schema::table('items', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }
};
