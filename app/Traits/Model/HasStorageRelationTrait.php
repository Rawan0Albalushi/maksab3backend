<?php

namespace App\Traits\Model;

use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use App\Support\Storage\FileStorage;
use App\Models\Storage;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasStorageRelationTrait
{
    public function initializeHasStorageRelationTrait(): void
    {
        $this->hidden[] = 'storage';
    }

    public function storage(): MorphMany
    {
        return $this->morphMany(Storage::class, 'data');
    }

    protected static function recordStorageDisk($model, string $column, ?string $key = null): void
    {
        if (! $model->isDirty($column)) {
            return;
        }

        $match = [
            'data_type' => get_class($model),
            'data_id' => $model->id,
        ];

        if ($key !== null) {
            $match['key'] = $key;
        }

        DB::table('storages')->updateOrInsert($match, [
            'value' => FileStorage::getDisk(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function attachmentUrls($value, string $folder = 'review', string $key = 'attachment'): array
    {
        $urls = [];

        foreach (Helpers::decodeJsonToArray($value ?? []) as $file) {
            if (! is_string($file) || $file === '') {
                continue;
            }

            $urls[] = $this->storageFullUrl($folder, $key, basename($file));
        }

        return $urls;
    }

    protected function storageFullUrl(string $folder, string $key, $value, ?string $placeholder = null, ?string $missingPlaceholder = null): ?string
    {
        if (blank($value) && ($missingPlaceholder === null || $missingPlaceholder === $placeholder)) {
            return Helpers::get_full_url($folder, $value, 'public', $placeholder);
        }

        foreach ($this->storage as $row) {
            if ($row['key'] === $key) {
                return Helpers::get_full_url($folder, $value, $row['value'], $placeholder);
            }
        }

        return Helpers::get_full_url($folder, $value, 'public', $missingPlaceholder ?? $placeholder);
    }
}
