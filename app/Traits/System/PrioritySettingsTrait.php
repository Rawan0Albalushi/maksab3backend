<?php

namespace App\Traits\System;

use App\Support\Cache\ApiCache;
use Illuminate\Support\Facades\Config;
use App\Services\System\PriorityListService;

trait PrioritySettingsTrait
{
    protected function findPrioritySetting(string $name, string $type, array $relations = [], bool $jsonDecode = false): mixed
    {
        try {
            $configKey = $name . '_' . $type . '_conf';

            if (Config::has($configKey)) {
                $data = Config::get($configKey);
            } else {
                $data = $this->allPrioritySettings()->where('name', $name)->where('type', $type)->first();

                if ($data && $relations) {
                    $data->loadMissing($relations);
                }

                Config::set($configKey, $data);
            }

            if (! isset($data['value'])) {
                return null;
            }

            $value = $data['value'];

            if ($jsonDecode && is_string($value)) {
                $decoded = json_decode($value, true);

                return is_null($decoded) ? $value : $decoded;
            }

            return $value;
        } catch (\Throwable) {
            return null;
        }
    }

    private function allPrioritySettings(): mixed
    {
        return ApiCache::remember(
            'priority_settings',
            'all_data',
            fn () => app(PriorityListService::class)->getAll()
        );
    }
}
