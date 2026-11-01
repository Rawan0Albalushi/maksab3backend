<?php

namespace App\Traits\Item;

trait ResolvesNamedIdsTrait
{
    abstract protected function namedIdModel(): string;

    abstract protected function namedIdColumn(): string;

    public function findOrCreateId(mixed $value): mixed
    {
        $model = $this->namedIdModel();
        $row = $model::firstOrNew([$this->namedIdColumn() => $value]);
        $row->save();

        return $row->id;
    }

    public function splitToIds(mixed $values): array
    {
        if ($values == null) {
            return [];
        }

        return array_map(fn ($value) => $this->findOrCreateId($value), explode(',', $values));
    }
}
