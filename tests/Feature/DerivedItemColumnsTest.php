<?php

namespace Tests\Feature;

use App\Observers\CategoryObserver;
use App\Observers\ItemObserver;
use App\Observers\StoreObserver;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * items.top_category_id and items.zone_id are derived columns with no fallback: the queries
 * reading them match on the column alone, and NULL matches nothing, so a row the observers
 * missed disappears from category counts and zone-scoped lists without raising anything.
 *
 * The observers only fire on Eloquent writes. The bulk imports write with DB::table(), which
 * fires no model events, so those paths have to call the sync helpers themselves -- these
 * sweeps check both that the data is consistent now and that no new bypassing write appears.
 */
class DerivedItemColumnsTest extends TestCase
{
    /** Offending ids to name in a failure message before giving up on the list. */
    private const SAMPLE = 10;

    /**
     * Writes stores without touching zone_id, so its items' zone cannot go stale.
     * Keyed by file, valued by the reason, so an entry has to justify itself.
     */
    private const WRITE_EXEMPT = [
        'app/Console/Commands/VendorPerfSweep.php' => 'updates module_id only',
    ];

    public function test_no_item_top_category_has_drifted(): void
    {
        $drifted = DB::select('
            SELECT i.id
            FROM items i
            JOIN categories c ON c.id = i.category_id
            WHERE i.top_category_id IS NULL
               OR i.top_category_id <> IF(c.parent_id = 0, c.id, c.parent_id)
            LIMIT '.self::SAMPLE);

        $this->assertSame([], array_column($drifted, 'id'), implode("\n", [
            'items.top_category_id disagrees with categories.parent_id for the ids above.',
            'Something wrote items or categories without going through ItemObserver /',
            'CategoryObserver -- most likely a DB::table() write that skipped the sync helper.',
        ]));
    }

    public function test_no_item_zone_has_drifted(): void
    {
        $drifted = DB::select('
            SELECT i.id
            FROM items i
            JOIN stores s ON s.id = i.store_id
            WHERE (i.zone_id IS NULL AND s.zone_id IS NOT NULL)
               OR (i.zone_id IS NOT NULL AND i.zone_id <> s.zone_id)
            LIMIT '.self::SAMPLE);

        $this->assertSame([], array_column($drifted, 'id'), implode("\n", [
            'items.zone_id disagrees with its store for the ids above.',
            'A store changed zone without reaching StoreObserver, or an item was written',
            'outside Eloquent without ItemObserver::syncDerived().',
        ]));
    }

    /**
     * The repair path itself, exercised the way an import leaves the table: columns wrong,
     * no model event fired. Runs inside a transaction that is always rolled back, because
     * these suites run against a populated database rather than a scratch one.
     */
    public function test_sync_derived_repairs_rows_written_outside_eloquent(): void
    {
        $item = DB::table('items')
            ->join('categories', 'categories.id', '=', 'items.category_id')
            ->join('stores', 'stores.id', '=', 'items.store_id')
            ->whereNotNull('items.top_category_id')
            ->whereNotNull('items.zone_id')
            ->select('items.id', 'items.top_category_id', 'items.zone_id')
            ->first();

        if (! $item) {
            $this->markTestSkipped('no item with a live category and store to repair');
        }

        DB::beginTransaction();

        try {
            DB::table('items')->where('id', $item->id)->update([
                'top_category_id' => null,
                'zone_id' => null,
            ]);

            ItemObserver::syncDerived([$item->id]);

            $repaired = DB::table('items')->where('id', $item->id)->first(['top_category_id', 'zone_id']);

            $this->assertSame((int) $item->top_category_id, (int) $repaired->top_category_id);
            $this->assertSame((int) $item->zone_id, (int) $repaired->zone_id);
        } finally {
            DB::rollBack();
        }
    }

    public function test_bulk_writes_call_the_matching_sync_helper(): void
    {
        $rules = [
            'items' => ItemObserver::class.'::syncDerived',
            'stores' => StoreObserver::class.'::syncItemZones',
            'categories' => CategoryObserver::class.'::syncItemTopCategories',
        ];

        $offenders = [];

        foreach ($this->sourceFiles() as $relative => $source) {
            foreach ($rules as $table => $helper) {
                if (! $this->writesTable($source, $table)) {
                    continue;
                }

                $call = class_basename(strtok($helper, ':')).'::'.substr(strrchr($helper, ':'), 1);

                if (! str_contains($source, $call) && ! isset(self::WRITE_EXEMPT[$relative])) {
                    $offenders[] = $relative.' writes '.$table.' with DB::table() but never calls '.$call.'()';
                }
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "%d bulk write(s) bypass the derived-column sync:\n%s",
            count($offenders),
            implode("\n", $offenders)
        ));
    }

    /** True when the source writes the table through the query builder rather than Eloquent. */
    private function writesTable(string $source, string $table): bool
    {
        return (bool) preg_match(
            '/DB::table\(\''.$table.'\'\)[^;\n]*->(insertGetId|insert|update|upsert)\(/',
            $source
        );
    }

    /** @return array<string, string> relative path => contents */
    private function sourceFiles(): array
    {
        $files = [];

        foreach (['app', 'Modules'] as $dir) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(base_path($dir), RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

                // The observers hold the sync SQL; they are the definition, not a caller.
                if (str_starts_with($relative, 'app/Observers/')) {
                    continue;
                }

                $files[$relative] = (string) file_get_contents($file->getPathname());
            }
        }

        return $files;
    }
}
