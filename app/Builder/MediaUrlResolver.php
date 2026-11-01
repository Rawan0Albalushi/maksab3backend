<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use Modules\Builder\Contracts\MediaUrlResolver as MediaUrlResolverContract;

class MediaUrlResolver implements MediaUrlResolverContract
{
    public function defaultLogoUrl(): ?string
    {
        try {
            return Helpers::logoFullUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    public function url(string $folder, string $filename, string $disk = 'public', ?string $type = null): string
    {
        return Helpers::get_full_url($folder, $filename, $disk, $type ?? 'upload_image');
    }

    public function assetUrl(string $path): string
    {
        return asset('public/' . \ltrim($path, '/'));
    }

    public function storageBaseUrl(): string
    {
        return asset('storage/app/public');
    }
}
