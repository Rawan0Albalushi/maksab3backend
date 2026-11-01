<?php

namespace App\Support\Promotion;

use App\Models\AddOn;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;

/**
 * "Extra Cheese (2)" for a set of frozen promotion lines, keyed by line id.
 *
 * A bundle line and a BOGO enrolment line both freeze the add-on IDS they were built with and
 * never their names — that is the point of freezing, so the line still reads correctly after an
 * add-on is renamed or deleted. Rendering one therefore needs a lookup, and both line models
 * already share `DescribesFrozenItemLine`, which owns the formatting.
 *
 * Keyed on LINES rather than on bundles because the two callers hold different parents: a
 * `Bundle` has `items`, a BOGO `enrollment` has its own. The lookup does not care which — it
 * needs `add_on_ids` and `addOnDisplayLines()`, and the trait gives both.
 *
 * ONE QUERY, whatever it is handed. Every caller renders a PAGE of parents, so resolving per
 * parent would be an N+1 across the list (architecture rule 11). Pass the whole page in.
 */
class AddOnLabels
{
    /**
     * @param  iterable<int, object>  $lines  anything using DescribesFrozenItemLine
     * @return array<int, array<int, string>>  line id => ["Extra Cheese (2)", …]
     */
    public static function forLines(iterable $lines): array
    {
        $lines = self::rows($lines);

        if ($lines->isEmpty()) {
            return [];
        }

        $ids = $lines->flatMap(fn ($line) => $line->add_on_ids ?? [])->unique()->filter()->all();

        $names = $ids
            ? AddOn::with('translations')->whereIn('id', $ids)->get()->pluck('name', 'id')->all()
            : [];

        // Lines with no add-ons still get a key, holding an empty array, so a caller can index by
        // line id without checking whether the key exists first.
        return $lines->mapWithKeys(fn ($line) => [$line->id => $line->addOnDisplayLines($names)])->all();
    }

    /**
     * The same, for parents that hold their lines in `items` — a Bundle, a BOGO enrolment.
     *
     * @param  iterable<int, object>  $parents
     * @return array<int, array<int, string>>
     */
    public static function forParents(iterable $parents): array
    {
        return self::forLines(
            self::rows($parents)->flatMap(fn ($parent) => $parent->items ?? [])
        );
    }

    /**
     * `collect($paginator)` returns the pagination ENVELOPE, not the rows — a paginator is
     * Arrayable, so Collection calls `toArray()` on it and you iterate `current_page`, `data`,
     * `from` and friends. Every caller today passes a Collection or an array, but "the page I
     * just built" is the obvious thing to hand a method typed `iterable`, and the failure is a
     * TypeError inside a closure rather than anything that reads like a mistake at the call site.
     *
     * The same guard as `BaseService::rowsOf()`; repeated here because this is a static support
     * class and cannot inherit it.
     */
    private static function rows(iterable $items): Collection
    {
        return collect($items instanceof AbstractPaginator || $items instanceof AbstractCursorPaginator
            ? $items->items()
            : $items);
    }
}
