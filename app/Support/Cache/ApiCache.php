<?php

namespace App\Support\Cache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ApiCache
{
    private const STAMP_PREFIX = 'cachestamp:';

    private const KEY_PREFIX = 'apicache:';

    private const NO_STAMP = '0';

    private const ENABLED = true;

    private static array $stampMemo = [];

    public static function enabled(): bool
    {
        return self::ENABLED;
    }

    public static function store(): Repository
    {
        $name = config('api_cache.store') ?: config('cache.default');

        if (! is_string($name) || ! config("cache.stores.{$name}")) {
            $name = config('cache.default');
        }

        return Cache::store($name);
    }

    public static function key(string $group, mixed $context = null, array $extraTags = []): string
    {
        $tags = array_values(array_unique(array_merge(self::tagsFor($group), $extraTags)));

        return self::KEY_PREFIX.$group.':'.self::stampToken($tags).':'.self::contextHash($context);
    }

    public static function remember(string $group, mixed $context, callable $callback, mixed $ttl = null, array $extraTags = []): mixed
    {
        if (! self::enabled()) {
            return $callback();
        }

        $ttl ??= self::ttlFor($group);
        $key = self::key($group, $context, $extraTags);

        return $ttl === null
            ? self::store()->rememberForever($key, $callback)
            : self::store()->remember($key, $ttl, $callback);
    }

    public static function get(string $group, mixed $context = null, mixed $default = null): mixed
    {
        return self::enabled() ? self::store()->get(self::key($group, $context), $default) : $default;
    }

    public static function put(string $group, mixed $context, mixed $value, mixed $ttl = null): void
    {
        if (! self::enabled()) {
            return;
        }

        $ttl ??= self::ttlFor($group);
        $key = self::key($group, $context);

        $ttl === null
            ? self::store()->forever($key, $value)
            : self::store()->put($key, $value, $ttl);
    }

    public static function has(string $group, mixed $context = null): bool
    {
        return self::enabled() && self::store()->has(self::key($group, $context));
    }

    public static function forget(string $group, mixed $context = null): void
    {
        self::store()->forget(self::key($group, $context));
    }

    public static function increment(string $group, mixed $context, int $by = 1): int
    {
        $current = (int) self::get($group, $context, 0);
        $next = $current + $by;

        self::put($group, $context, $next);

        return $next;
    }

    public static function bust(string ...$tags): void
    {
        foreach (array_filter($tags) as $tag) {
            unset(self::$stampMemo[$tag]);
            self::store()->forever(self::STAMP_PREFIX.$tag, self::newToken());
        }
    }

    public static function bustPrefix(string $prefix): void
    {
        self::bust(...self::tagsForPrefix($prefix));
    }

    public static function bustAll(): void
    {
        self::bust(...self::allTags());
    }

    public static function allTags(): array
    {
        $tags = [];

        foreach ((array) config('api_cache.groups', []) as $group => $definition) {
            foreach ((array) ($definition['tags'] ?? [$group]) as $tag) {
                $tags[$tag] = true;
            }
        }

        foreach ((array) config('api_cache.legacy_prefix_tags', []) as $mapped) {
            foreach ((array) $mapped as $tag) {
                $tags[$tag] = true;
            }
        }

        return array_keys($tags);
    }

    public static function tagsForPrefix(string $prefix): array
    {
        $map = (array) config('api_cache.legacy_prefix_tags', []);

        if (isset($map[$prefix])) {
            return (array) $map[$prefix];
        }

        $best = null;

        foreach ($map as $candidate => $tags) {
            if (! str_starts_with($prefix, (string) $candidate)) {
                continue;
            }

            if ($best === null || strlen((string) $candidate) > strlen((string) $best)) {
                $best = $candidate;
            }
        }

        return $best !== null ? (array) $map[$best] : [self::fallbackTag($prefix)];
    }

    public static function tagsFor(string $group): array
    {
        $tags = self::definition($group)['tags'] ?? null;

        return empty($tags) ? [$group] : (array) $tags;
    }

    public static function ttlFor(string $group): ?int
    {
        $ttl = self::definition($group)['ttl'] ?? null;

        return $ttl === null ? null : (int) $ttl;
    }

    private static function definition(string $group): array
    {
        $groups = (array) config('api_cache.groups', []);

        return (array) ($groups[$group] ?? []);
    }

    public static function flush(): void
    {
        self::$stampMemo = [];
        self::store()->flush();
    }

    public static function forgetMemo(): void
    {
        self::$stampMemo = [];
    }

    private static function stampToken(array $tags): string
    {
        sort($tags);

        $missing = array_values(array_diff($tags, array_keys(self::$stampMemo)));

        if ($missing !== []) {
            $fetched = self::store()->many(array_map(
                fn (string $tag): string => self::STAMP_PREFIX.$tag,
                $missing
            ));

            foreach ($missing as $tag) {
                self::$stampMemo[$tag] = (string) ($fetched[self::STAMP_PREFIX.$tag] ?? self::NO_STAMP);
            }
        }

        $stamps = [];

        foreach ($tags as $tag) {
            $stamps[] = self::$stampMemo[$tag];
        }

        return substr(md5(implode('|', $stamps)), 0, 12);
    }

    private static function contextHash(mixed $context): string
    {
        if ($context === null) {
            return 'default';
        }

        if (is_string($context) && $context !== '' && strlen($context) <= 64 && ! str_contains($context, ':')) {
            return $context;
        }

        return md5(is_scalar($context) ? (string) $context : serialize($context));
    }

    private static function fallbackTag(string $prefix): string
    {
        return trim(Str::slug($prefix, '_'), '_') ?: 'unmapped';
    }

    private static function newToken(): string
    {
        return uniqid(more_entropy: true);
    }
}
