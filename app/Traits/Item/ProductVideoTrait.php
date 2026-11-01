<?php

namespace App\Traits\Item;

use App\CentralLogics\Helpers;
use App\Models\Item;
use App\Models\TempProduct;
use Illuminate\Http\UploadedFile;
use App\Support\Storage\FileStorage;

trait ProductVideoTrait
{

    protected function requestedVideoType(array $data, ?string $videoLink = null): string
    {
        return ($data['video_upload_type'] ?? null) ?: ($videoLink ? 'link' : 'file');
    }

    protected function normalizedVideoLink(mixed $videoLink): ?string
    {
        $videoLink = trim((string) $videoLink);

        return $videoLink !== '' ? $videoLink : null;
    }

    protected function uploadedVideo(array $data): ?UploadedFile
    {
        return ($data['video'] ?? null) instanceof UploadedFile ? $data['video'] : null;
    }

    protected function removesVideo(array $data): bool
    {
        return (int) ($data['remove_video'] ?? 0) === 1;
    }

    protected function persistedVideoData(array $data, ?string $currentVideo = null, ?string $currentVideoLink = null): array
    {
        if ($this->requestedVideoType($data, $currentVideoLink) === 'link') {
            return ['video' => null, 'video_link' => $this->normalizedVideoLink($data['video_link'] ?? null)];
        }

        if ($video = $this->uploadedVideo($data)) {
            return [
                'video' => $currentVideo
                    ? FileStorage::update('product/', $currentVideo, $video, Helpers::productVideoMaxUploadSizeMb(), VIDEO_EXTENSION)
                    : FileStorage::upload('product/', $video, Helpers::productVideoMaxUploadSizeMb(), VIDEO_EXTENSION),
                'video_link' => null,
            ];
        }

        if ($this->removesVideo($data)) {
            return ['video' => null, 'video_link' => null];
        }

        return ['video' => $currentVideo, 'video_link' => null];
    }

    protected function createdVideoData(array $data, ?Item $gallerySource = null): array
    {
        if (! $gallerySource || ! ($data['item_id'] ?? null) || (int) ($data['product_gellary'] ?? 0) !== 1) {
            return $this->persistedVideoData($data);
        }

        if ($this->uploadedVideo($data) || $this->removesVideo($data)) {
            return $this->persistedVideoData($data);
        }

        if (($data['video_upload_type'] ?? null) === 'link' && $this->normalizedVideoLink($data['video_link'] ?? null)) {
            return $this->persistedVideoData($data);
        }

        $galleryVideo = Helpers::duplicateProductVideoData($gallerySource);

        return $this->persistedVideoData($data, $galleryVideo['video'], $galleryVideo['video_link']);
    }

    protected function tempProductVideoData(array $data, TempProduct $tempItem, Item $item): array
    {
        $tempHasVideo = $tempItem->exists && ($tempItem->video !== null || $tempItem->video_link !== null);

        if ($this->requestedVideoType($data, $tempHasVideo ? $tempItem->video_link : $item->video_link) === 'link') {
            $this->discardTempVideo($tempItem);

            return ['video' => null, 'video_link' => $this->normalizedVideoLink($data['video_link'] ?? null)];
        }

        if ($video = $this->uploadedVideo($data)) {
            $this->discardTempVideo($tempItem);

            return [
                'video' => FileStorage::upload('product/', $video, Helpers::productVideoMaxUploadSizeMb(), VIDEO_EXTENSION),
                'video_link' => null,
            ];
        }

        if ($this->removesVideo($data)) {
            $this->discardTempVideo($tempItem);

            return ['video' => null, 'video_link' => null];
        }

        if ($tempHasVideo) {
            return ['video' => $tempItem->video, 'video_link' => $tempItem->video_link];
        }

        if ($item->video_link) {
            $this->discardTempVideo($tempItem);

            return ['video' => null, 'video_link' => $item->video_link];
        }

        if ($item->video) {
            $copiedVideo = FileStorage::copyStorageFile('product/', $item->video, FileStorage::getStorageDiskByKey($item, 'video', 'public'));

            if ($tempItem->video && $tempItem->video !== $copiedVideo) {
                FileStorage::delete('product/', $tempItem->video);
            }

            return ['video' => $copiedVideo, 'video_link' => null];
        }

        return ['video' => null, 'video_link' => null];
    }

    private function discardTempVideo(TempProduct $tempItem): void
    {
        if ($tempItem->video) {
            FileStorage::delete('product/', $tempItem->video);
        }
    }
}
