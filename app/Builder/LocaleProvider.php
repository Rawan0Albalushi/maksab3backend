<?php

namespace App\Builder;

use App\Models\BusinessSetting;
use Modules\Builder\Contracts\LocaleProvider as LocaleProviderContract;

class LocaleProvider implements LocaleProviderContract
{
    public function availableLanguages(): array
    {
        try {
            $setting = BusinessSetting::where('key', 'system_language')->first();

            if (!$setting) {
                return [self::baselineEnglish()];
            }

            return collect(json_decode($setting->value, true))
                ->where('status', 1)
                ->map(fn ($language) => [
                    'code'      => $language['code'] ?? 'en',
                    'name'      => $language['name'] ?? strtoupper((string) ($language['code'] ?? 'en')),
                    'direction' => $language['direction'] ?? 'ltr',
                    'default'   => (bool) ($language['default'] ?? false),
                    'flag'      => self::flagUrl($language['code'] ?? 'en'),
                ])
                ->values()
                ->toArray();
        } catch (\Throwable) {
            return [self::baselineEnglish()];
        }
    }

    private static function baselineEnglish(): array
    {
        return ['code' => 'en', 'name' => 'English', 'direction' => 'ltr', 'default' => true, 'flag' => self::flagUrl('en')];
    }

    private static function flagUrl(string $code): string
    {
        return asset('public/assets/admin/img/flags/' . strtolower($code) . '.png');
    }
}
