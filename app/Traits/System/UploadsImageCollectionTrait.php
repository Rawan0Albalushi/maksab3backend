<?php

namespace App\Traits\System;

use App\Support\Storage\FileStorage;

trait UploadsImageCollectionTrait
{
    protected function uploadImageCollection(array $images, string $dir): array
    {
        $uploaded = [];

        foreach ($images as $image) {
            $uploaded[] = ['img' => FileStorage::upload($dir, $image), 'storage' => FileStorage::getDisk()];
        }

        return $uploaded;
    }
}
