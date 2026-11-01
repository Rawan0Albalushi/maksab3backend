<?php

namespace App\Traits\Item;

use App\Models\Item;
use App\Models\TempProduct;
use Illuminate\Http\UploadedFile;
use App\Services\Item\TempProductService;
use App\Support\Storage\FileStorage;

trait TempProductTrait
{
    protected function storeTempProduct(Item $item, array $data, array $taxonomyIds, string $moduleType, bool $update = false, array $taxIds = []): void
    {
        $temp = app(TempProductService::class)->findOrNewForItem($item->id);
        $oldImage = $temp->image ?? null;
        $translations = $data['translations'];

        $temp->name = $translations[0]['value'];
        $temp->description = $translations[1]['value'];

        $temp->store_id = $item->store_id;
        $temp->module_id = $item->module_id;
        $temp->unit_id = $item->unit_id;
        $temp->item_id = $item->id;

        $temp->category_id = $item->category_id;
        $temp->category_ids = $item->category_ids;
        $temp->store_category_id = $item->store_category_id;
        $temp->slug = $item->slug;

        $temp->choice_options = $item->choice_options;
        $temp->food_variations = $item->food_variations;
        $temp->variations = $item->variations;
        $temp->add_ons = $item->add_ons;
        $temp->attributes = $item->attributes;

        $temp->price = $item->price;
        $temp->discount = $item->discount;
        $temp->discount_type = $item->discount_type;
        $temp->tag_ids = json_encode($taxonomyIds['tags']);
        $temp->nutrition_ids = json_encode($taxonomyIds['nutritions']);
        $temp->allergy_ids = json_encode($taxonomyIds['allergies']);
        $temp->generic_ids = json_encode($taxonomyIds['generics']);

        $temp->available_time_starts = $item->available_time_starts;
        $temp->available_time_ends = $item->available_time_ends;
        $temp->maximum_cart_quantity = $item->maximum_cart_quantity;
        $temp->veg = $item->veg ?? 0;
        $temp->organic = $item->organic ?? 0;
        $temp->stock = $item->stock ?? 0;
        $temp->common_condition_id = $data['condition_id'] ?? 0;
        $temp->brand_id = $data['brand_id'] ?? 0;
        $temp->is_halal = $data['is_halal'] ?? 0;
        $temp->is_prescription_required = $data['is_prescription_required'] ?? 0;
        $temp->basic = $data['basic'] ?? 0;

        $temp->image = $this->tempProductImage($data, $item, $oldImage, $temp->image);

        $videoData = $this->tempProductVideoData($data, $temp, $item);
        $temp->video = $videoData['video'];
        $temp->video_link = $videoData['video_link'];

        $temp->images = $this->tempProductImages($data, $item, $temp, $update);

        if ($update) {
            $temp->is_rejected = 0;
        }

        $temp->save();

        if ($moduleType === 'pharmacy') {
            $temp->pharmacy_item_details()->updateOrCreate([], [
                'common_condition_id' => $data['condition_id'] ?? null,
                'is_basic' => $data['basic'] ?? 0,
                'is_prescription_required' => $data['is_prescription_required'] ?? 0,
                'unit_value' => $data['unit_value'] ?? null,
                'manufacturer' => $data['manufacturer'] ?? null,
                'item_id' => null,
            ]);
        }

        if (in_array($moduleType, ['ecommerce', 'grocery'], true)) {
            $temp->ecommerce_item_details()->updateOrCreate([], [
                'brand_id' => $data['brand_id'] ?? null,
                'item_id' => null,
            ]);
        }

        if ($moduleType === 'ecommerce') {
            $this->syncItemSeoData($data, $temp->id, temp: true);
        }

        foreach ($translations as $translation) {
            $temp->translations()->updateOrCreate(
                ['locale' => $translation['locale'], 'key' => $translation['key']],
                ['value' => $translation['value']]
            );
        }

        $this->createTaxables($temp, $taxIds);
    }
    protected function uploadedImages(array $data): array
    {
        $images = $data['item_images'] ?? [];

        return is_array($images) ? $images : [$images];
    }

    private function tempProductImage(array $data, Item $item, ?string $oldImage, ?string $currentImage): ?string
    {
        if (($data['image'] ?? null) instanceof UploadedFile) {
            return $oldImage
                ? FileStorage::update('product/', $oldImage, $data['image'])
                : FileStorage::upload('product/', $data['image']);
        }

        if ($currentImage !== null || $item->image === null) {
            return $currentImage;
        }

        return FileStorage::copyStorageFile('product/', $item->image, FileStorage::getStorageDiskByKey($item, 'image', 'public')) ?? $currentImage;
    }
    private function tempProductImages(array $data, Item $item, TempProduct $temp, bool $update): array
    {
        $images = ($temp->exists && $temp->images !== null) ? $temp->images : ($item->images ?? []);

        if ($data['removedImageKeys'] ?? null) {
            $removed = $this->decodedInput($data['removedImageKeys']);
            $images = array_values(array_filter(
                $images,
                fn ($value) => ! in_array(is_array($value) ? $value['img'] : $value, $removed)
            ));
        }

        $copied = [];
        foreach ($images as $value) {
            $value = is_array($value) ? $value : ['img' => $value, 'storage' => 'public'];
            $newFileName = FileStorage::copyStorageFile('product/', $value['img'], $value['storage']);
            $copied[] = $newFileName ? ['img' => $newFileName, 'storage' => FileStorage::getDisk()] : $value;
        }

        if ($update) {
            foreach ($this->uploadedImages($data) as $image) {
                $copied[] = ['img' => FileStorage::upload('product/', $image), 'storage' => FileStorage::getDisk()];
            }
        }

        return $copied;
    }
}
