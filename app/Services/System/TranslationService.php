<?php

namespace App\Services\System;

use App\Models\Translation;
use App\Services\BaseService;
use Illuminate\Support\Facades\Log;

class TranslationService extends BaseService
{
    public function addOrUpdate($request, $keyData, $nameField, $modelName, $dataId, $dataValue, $modelClass = false): bool
    {
        try {
            $model = $modelClass === true ? $modelName : 'App\\Models\\'.$modelName;
            $defaultLang = str_replace('_', '-', app()->getLocale());

            $locales = data_get($request, 'lang');
            $values = data_get($request, $nameField);

            if (! is_array($locales)) {
                return false;
            }

            foreach ($locales as $index => $key) {
                if ($key === 'default') {
                    continue;
                }

                $value = data_get($values, $index);
                $isEmptyDefault = $defaultLang == $key && ! $value;

                if ($isEmptyDefault) {
                    $this->store($model, $dataId, $key, $keyData, $dataValue);
                } elseif ($value) {
                    $this->store($model, $dataId, $key, $keyData, $value);
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('translation.save_failed', [
                'model' => $modelName,
                'data_id' => $dataId,
                'key' => $keyData,
                'field' => $nameField,
                'error' => $e->getMessage(),
                'at' => $e->getFile().':'.$e->getLine(),
            ]);

            return false;
        }
    }

    private function store(string $model, mixed $dataId, string $locale, string $key, mixed $value): void
    {
        Translation::updateOrCreate(
            [
                'translationable_type' => $model,
                'translationable_id' => $dataId,
                'locale' => $locale,
                'key' => $key,
            ],
            ['value' => $value]
        );
    }
}
