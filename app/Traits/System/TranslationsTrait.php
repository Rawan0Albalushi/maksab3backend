<?php

namespace App\Traits\System;

use Illuminate\Database\Eloquent\Model;

trait TranslationsTrait
{
    protected function insertTranslations(Model $model, array $rows): void
    {
        $rows = $this->buildTranslationRows($model, $rows);

        if (empty($rows)) {
            return;
        }

        $model->translations()->insert($rows);
    }

    protected function syncTranslations(Model $model, array $rows): void
    {
        foreach ($this->buildTranslationRows($model, $rows) as $row) {
            $model->translations()->updateOrCreate(
                ['locale' => $row['locale'], 'key' => $row['key']],
                ['value' => $row['value']]
            );
        }
    }

    private function buildTranslationRows(Model $model, array $rows): array
    {
        $prepared = [];

        foreach ($rows as $row) {
            if (! isset($row['locale'], $row['key'])) {
                continue;
            }

            $prepared[] = [
                'translationable_type' => $model->getMorphClass(),
                'translationable_id' => $model->getKey(),
                'locale' => $row['locale'],
                'key' => $row['key'],
                'value' => $row['value'] ?? null,
            ];
        }

        return $prepared;
    }
}
