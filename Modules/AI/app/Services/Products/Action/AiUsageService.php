<?php

namespace Modules\AI\app\Services\Products\Action;

use App\CentralLogics\Helpers;
use App\Models\StoreConfig;
use App\Services\BaseService;

class AiUsageService extends BaseService
{
    private const IMAGE_REQUEST = 'image';
    private const SECTION_COUNTER = 'section_wise_ai_use_count';
    private const IMAGE_COUNTER = 'image_wise_ai_use_count';
    private const SECTION_LIMIT_KEY = 'section_wise_ai_limit';
    private const IMAGE_LIMIT_KEY = 'image_upload_limit_for_ai';
    public function limitMessage(mixed $storeId, ?string $requestType): ?string
    {
        if (! $storeId) {
            return null;
        }

        if ($requestType === self::IMAGE_REQUEST) {
            return $this->withinLimit($storeId, self::IMAGE_LIMIT_KEY, self::IMAGE_COUNTER)
                ? null
                : translate('You have reached the limit of AI usage via image.');
        }

        return $this->withinLimit($storeId, self::SECTION_LIMIT_KEY, self::SECTION_COUNTER)
            ? null
            : translate('You have reached the limit of AI usage.');
    }
    public function recordUsage(mixed $storeId, ?string $requestType, int $sectionUses, int $imageUses = 0): void
    {
        $isImage = $requestType === self::IMAGE_REQUEST;
        $amount = $isImage ? $imageUses : $sectionUses;

        if (! $storeId || $amount < 1) {
            return;
        }

        $counter = $isImage ? self::IMAGE_COUNTER : self::SECTION_COUNTER;
        $config = $this->forStoreQuery($storeId)->firstOrNew(['store_id' => $storeId]);

        if ($config->exists) {
            $config->increment($counter, $amount);

            return;
        }

        $config->fill([$counter => $amount])->save();
    }
    private function forStoreQuery(mixed $storeId): mixed
    {
        return StoreConfig::where('store_id', $storeId);
    }
    private function withinLimit(mixed $storeId, string $settingKey, string $counter): bool
    {
        $limit = Helpers::get_business_settings($settingKey);

        if (! $limit) {
            return false;
        }

        return $limit > (int) $this->forStoreQuery($storeId)->value($counter);
    }
}
