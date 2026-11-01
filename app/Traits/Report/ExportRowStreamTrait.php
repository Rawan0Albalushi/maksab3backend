<?php

namespace App\Traits\Report;

use Illuminate\Support\LazyCollection;

trait ExportRowStreamTrait
{
    protected const EXPORT_CHUNK = 500;

    /**
     * The caller's query must carry a total ordering. forPage() re-sorts on every page, so a
     * non-unique sort column lets MySQL place a tied row on both sides of a page boundary,
     * duplicating one row and dropping another.
     */
    protected function streamExportRows($query, ?callable $each = null): LazyCollection
    {
        return LazyCollection::make(function () use ($query, $each) {
            $page = 1;

            do {
                $chunk = (clone $query)->forPage($page, self::EXPORT_CHUNK)->get();

                foreach ($chunk as $row) {
                    if ($each) {
                        $each($row);
                    }

                    yield $row;
                }

                $page++;
            } while ($chunk->count() === self::EXPORT_CHUNK);
        });
    }
}
