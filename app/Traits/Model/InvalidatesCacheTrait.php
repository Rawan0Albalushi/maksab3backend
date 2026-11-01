<?php

namespace App\Traits\Model;

use App\Support\Cache\ApiCache;
use Illuminate\Database\Eloquent\SoftDeletes;

trait InvalidatesCacheTrait
{
    public static function bootInvalidatesCacheTrait(): void
    {
        $model = static::class;

        $bust = static function () use ($model): void {
            $tags = $model::cacheTags();

            if ($tags !== []) {
                ApiCache::bust(...$tags);
            }
        };

        static::saved($bust);
        static::deleted($bust);

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            static::registerModelEvent('restored', $bust);
            static::registerModelEvent('forceDeleted', $bust);
        }
    }

    public static function cacheTags(): array
    {
        return property_exists(static::class, 'cacheTags') ? array_values((array) static::$cacheTags) : [];
    }
}
