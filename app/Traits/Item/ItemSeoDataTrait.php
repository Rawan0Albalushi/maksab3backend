<?php

namespace App\Traits\Item;

use App\CentralLogics\Helpers;
use Illuminate\Http\UploadedFile;
use App\Services\Item\ItemSeoDataService;
use App\Services\Item\TempProductService;
use App\Support\Storage\FileStorage;

trait ItemSeoDataTrait
{

    protected function syncItemSeoData(array $data, mixed $itemId, bool $temp = false): void
    {
        $seoData = $temp
            ? app(ItemSeoDataService::class)->updateOrCreateForTempItem($itemId)
            : app(ItemSeoDataService::class)->updateOrCreateForItem($itemId);

        $uploadedImage = ($data['meta_image'] ?? null) instanceof UploadedFile ? $data['meta_image'] : null;

        if ($temp && ! $seoData->image && ! $uploadedImage) {
            $originalItemId = app(TempProductService::class)->findItemId($itemId);
            $seoData->image = $originalItemId
                ? app(ItemSeoDataService::class)->findImage($originalItemId)
                : $seoData->image;
        }

        if ((int) ($data['meta_image_deleted'] ?? 0) === 1) {
            FileStorage::delete('item_meta_data/', $seoData->image);
            $seoData->image = null;
        }

        $seoData->title = $data['meta_title'] ?? null;
        $seoData->description = $data['meta_description'] ?? null;
        $seoData->image = $uploadedImage
            ? FileStorage::upload(dir: 'item_meta_data/', image: $uploadedImage)
            : $seoData->image;
        $seoData->meta_data = Helpers::formatMetaData($data['inputs'] ?? [], $seoData->meta_data);

        $seoData->save();
    }
}
