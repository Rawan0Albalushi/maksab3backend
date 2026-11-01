<?php

namespace App\Services\Marketing;

use App\Models\DataSetting;
use App\Models\ReactPromotionalBanner;
use Illuminate\Support\Facades\DB;
use App\Services\BaseService;

class ReactPromotionalBannerService extends BaseService
{
    public function migrateFromLegacyDataSetting(): bool
    {
        $exists = DataSetting::where([
            'key' => 'promotion_banner',
            'type' => 'react_landing_page',
        ])->exists();

        if (! $exists) {
            return false;
        }

        return DB::transaction(function () {
            $oldBanners = DataSetting::where([
                'key' => 'promotion_banner',
                'type' => 'react_landing_page',
            ])->first();

            $newRecords = [];
            $banners = json_decode($oldBanners->value, true);

            if (is_array($banners)) {
                foreach ($banners as $banner) {
                    if (! empty($banner['img'])) {
                        $newRecords[] = [
                            'image' => $banner['img'],
                            'status' => 1,
                        ];
                    }
                }
            }

            if (! empty($newRecords)) {
                ReactPromotionalBanner::upsert($newRecords, ['image'], ['status']);
            }

            $oldBanners->delete();

            return true;
        });
    }

    public function getActiveImageUrls(): array
    {
        // image_full_url reads the storage relation, so mapping over the rows without it is one
        // query per banner -- and a lazy-load exception outside production.
        return ReactPromotionalBanner::with(['storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS)])
            ->where('status', 1)->get()
            ->map(fn ($banner) => $banner->image_full_url)->all();
    }
}
