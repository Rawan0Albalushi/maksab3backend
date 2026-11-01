<?php

namespace App\Services;

use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BaseService
{
    protected function pageSize(array $paginate = []): int
    {
        $perPage = (int) ($paginate['per_page'] ?? config('default_pagination'));

        return $perPage > 0 ? $perPage : (int) config('default_pagination');
    }

    protected function pageNumber(array $paginate = []): int
    {
        return max(1, (int) ($paginate['page'] ?? 1));
    }

    /**
     * The rows out of whatever a caller handed over — a Collection, an array, or a paginator.
     *
     * `collect($paginator)` does NOT give you the rows. A paginator is `Arrayable`, so Collection
     * calls `toArray()` on it and you get the pagination ENVELOPE instead: `current_page`,
     * `data`, `from`, `last_page` and friends. Iterating that hands a callback the integer 1
     * where it expected a model, which is exactly how
     * `DeliveryRuleService::lockedModulesFor()` came to be called with an int.
     *
     * Any service method typed `iterable $rows` should normalise through this, because "the page
     * I just built" is the most natural thing for a controller to pass and the failure is a
     * TypeError deep inside a closure rather than anything that reads like a mistake at the
     * call site.
     */
    protected function rowsOf(iterable $items): Collection
    {
        return collect($items instanceof AbstractPaginator || $items instanceof AbstractCursorPaginator
            ? $items->items()
            : $items);
    }

    protected function paginateCollection(mixed $items, array $paginate = []): LengthAwarePaginator
    {
        $items = $items instanceof Collection ? $items : collect($items);
        $perPage = $this->pageSize($paginate);
        $page = $this->pageNumber($paginate);

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page
        );
    }
}
