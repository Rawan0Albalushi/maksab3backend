<?php

namespace App\Services\Item;

use App\Models\ItemSeoData;
use App\Services\BaseService;

class ItemSeoDataService extends BaseService
{
    public function updateOrCreateForTempItem(mixed $tempItemId): ItemSeoData
    {
        return ItemSeoData::updateOrCreate(['temp_item_id' => $tempItemId], ['item_id' => null]);
    }

    public function updateOrCreateForItem(mixed $itemId): ItemSeoData
    {
        return ItemSeoData::updateOrCreate(['item_id' => $itemId]);
    }

    public function findImage(mixed $itemId): mixed
    {
        return ItemSeoData::where('item_id', $itemId)->value('image');
    }
}
