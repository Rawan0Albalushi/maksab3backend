<?php

namespace App\Services\Item;

use App\Models\Tag;
use App\Services\BaseService;
use App\Traits\Item\ResolvesNamedIdsTrait;
use App\Traits\System\MemoizesLookupsTrait;

class TagService extends BaseService
{
    use MemoizesLookupsTrait;
    use ResolvesNamedIdsTrait;

    protected function namedIdModel(): string
    {
        return Tag::class;
    }

    protected function namedIdColumn(): string
    {
        return 'tag';
    }

    public function getByIds(array $ids, array $columns = ['*']): mixed
    {
        return Tag::whereIn('id', $ids)->get($columns);
    }

    public function getMemoizedByIds(mixed $ids): mixed
    {
        $ids = array_values(array_filter((array) $ids, fn ($id) => is_numeric($id)));

        if (! $ids) {
            return collect();
        }

        $map = $this->memoize('tags', fn () => Tag::get(['id', 'tag'])->keyBy('id'));

        return $map->only($ids)->values();
    }
}
