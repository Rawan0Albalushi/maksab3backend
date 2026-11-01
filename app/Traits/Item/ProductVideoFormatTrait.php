<?php

namespace App\Traits\Item;

trait ProductVideoFormatTrait
{
    protected function productVideoFields(mixed $item): array
    {
        return [
            'video' => $item?->video,
            'video_full_url' => $item?->video_full_url,
            'video_link' => $item?->video_link,
            'video_source_type' => match (true) {
                (bool) $item?->video_full_url => 'upload',
                (bool) $item?->video_link => 'link',
                default => 'none',
            },
            'video_preview_type' => $item?->video_preview_type,
            'video_preview_url' => $item?->video_preview_url,
            'video_thumbnail_url' => $item?->video_thumbnail_url,
            'video_embed_url' => $item?->video_embed_url,
            'video_preview_available' => (bool) $item?->video_preview_available,
            'video_unavailable_reason' => $item?->video_unavailable_reason,
        ];
    }
}
