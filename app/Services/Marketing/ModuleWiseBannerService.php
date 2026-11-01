<?php

namespace App\Services\Marketing;

use App\Models\ModuleWiseBanner;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class ModuleWiseBannerService extends BaseService
{
    private const PROMOTIONAL_TYPE = 'promotional_banner';

    private const VIDEO_TYPE = 'video_banner_content';

    private const VIDEO_BANNER_KEYS = ['banner_type', 'banner_video', 'banner_image', 'banner_video_content'];

    private const VIDEO_CONTENT_KEYS = [
        'content1_title', 'content1_subtitle',
        'content2_title', 'content2_subtitle',
        'content3_title', 'content3_subtitle',
    ];

    private const LIST_COLUMNS = ['id', 'key', 'value'];

    public function getPromotionalBanners(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->baseQuery($filters['module_id'] ?? null, self::PROMOTIONAL_TYPE)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getVideoContent(array $filters = []): array
    {
        $rows = $this->baseQuery($filters['module_id'] ?? null, self::VIDEO_TYPE)
            ->whereIn('key', [...self::VIDEO_BANNER_KEYS, ...self::VIDEO_CONTENT_KEYS])
            ->get()
            ->keyBy('key')
            ->toBase();

        return [
            'banners' => $rows->only(self::VIDEO_BANNER_KEYS)->all(),
            'contents' => $rows->only(self::VIDEO_CONTENT_KEYS)->values()->all(),
        ];
    }

    private function baseQuery(mixed $moduleId, string $type): mixed
    {
        return ModuleWiseBanner::with([
            'storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS),
            'translations' => fn ($query) => $query->where('locale', app()->getLocale())
                ->select(TRANSLATION_RELATION_COLUMNS),
        ])
            ->withoutGlobalScope('translate')
            ->active()
            ->where('module_id', $moduleId)
            ->where('type', $type)
            ->select(self::LIST_COLUMNS);
    }
}
