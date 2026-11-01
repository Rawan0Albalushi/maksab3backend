<?php

namespace App\Services\Search;

use Closure;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionMethod;

class RouteIndex
{
    private const CACHE_TTL_MINUTES = 1440;

    private array $entries = [];

    private array $memo = [];

    private static array $rawCache = [];

    public function __construct(string $prefix, array $excludedTerms = [], ?Closure $can = null)
    {
        foreach (self::rawEntries($prefix) as $entry) {
            foreach ($excludedTerms as $term) {
                if ($term !== '' && str_contains($entry['uri'], $term)) {
                    continue 2;
                }
            }

            if ($can !== null && $entry['permission'] !== '' && ! $can($entry['permission'])) {
                continue;
            }

            $this->entries[] = $entry;
        }
    }

    public function all(): array
    {
        return $this->entries;
    }

    public function where(string $key, callable $filter): array
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = array_values(array_filter($this->entries, function ($entry) use ($filter) {
                return $filter($entry['uri'], $entry['name']);
            }));
        }

        return $this->memo[$key];
    }

    public static function resolvableUris(string $prefix, array $candidates): array
    {
        $entries = self::rawEntries($prefix);
        $literal = [];
        foreach ($entries as $entry) {
            $literal[$entry['uri']] = $entry['permission'];
        }

        $unknown = [];
        $allowed = [];
        foreach ($candidates as $uri) {
            if ($uri === '' || str_contains($uri, '{')) {
                continue;
            }
            $path = explode('?', $uri)[0];
            if (isset($literal[$path])) {
                $allowed[$uri] = $literal[$path];
            } else {
                $unknown[$uri] = true;
            }
        }

        if ($unknown === []) {
            return $allowed;
        }

        $key = 'search:resolvable-uris:' . md5($prefix . '|' . self::signature($entries) . '|' . implode('|', array_keys($unknown)));

        $matched = ApiCache::remember('route_index', $key, function () use ($prefix, $unknown) {
            return self::matchAgainstPatterns($prefix, array_keys($unknown));
        });

        foreach ($matched as $uri => $permission) {
            $allowed[$uri] = $permission;
        }

        return $allowed;
    }

    public static function jsonResponseUris(string $prefix): array
    {
        $entries = self::rawEntries($prefix);

        return ApiCache::remember(
            'route_index',
            'json-response-uris:' . md5($prefix . '|' . self::signature($entries)),
            function () use ($entries) {
                return self::scanJsonResponseUris($entries);
            }
        );
    }

    private static function permissionOf($route): string
    {
        foreach ($route->middleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }
            if (str_starts_with($middleware, 'module:')) {
                return substr($middleware, 7);
            }
            if (str_contains($middleware, 'ModulePermissionMiddleware:')) {
                return Str::afterLast($middleware, ':');
            }
        }

        return '';
    }

    private static function rawEntries(string $prefix): array
    {
        if (isset(self::$rawCache[$prefix])) {
            return self::$rawCache[$prefix];
        }

        $routes = [];
        foreach (Route::getRoutes()->getRoutesByMethod()['GET'] ?? [] as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, $prefix)) {
                continue;
            }

            $routes[] = [
                'uri' => $uri,
                'name' => (string) $route->getName(),
                'controller' => $route->getAction()['controller'] ?? null,
                'permission' => self::permissionOf($route),
            ];
        }

        return self::$rawCache[$prefix] = $routes;
    }

    private static function matchAgainstPatterns(string $prefix, array $uris): array
    {
        $patterns = [];
        foreach (Route::getRoutes()->getRoutesByMethod()['GET'] ?? [] as $route) {
            if (! str_starts_with($route->uri(), $prefix) || ! str_contains($route->uri(), '{')) {
                continue;
            }

            try {
                $patterns[] = [$route->toSymfonyRoute()->compile()->getRegex(), self::permissionOf($route)];
            } catch (\Throwable) {
                continue;
            }
        }

        $matched = [];
        foreach ($uris as $uri) {
            $path = '/' . ltrim(explode('?', $uri)[0], '/');
            foreach ($patterns as [$pattern, $permission]) {
                if (preg_match($pattern, $path)) {
                    $matched[$uri] = $permission;
                    break;
                }
            }
        }

        return $matched;
    }

    private static function signature(array $entries): string
    {
        return md5(implode('|', array_map(function ($entry) {
            return $entry['uri'] . '@' . $entry['controller'];
        }, $entries)));
    }

    private static function scanJsonResponseUris(array $entries): array
    {
        $sources = [];
        $uris = [];

        foreach ($entries as $entry) {
            if (! $entry['controller'] || ! str_contains($entry['controller'], '@')) {
                continue;
            }

            [$class, $method] = explode('@', $entry['controller']);

            if (! class_exists($class) || ! method_exists($class, $method)) {
                continue;
            }

            try {
                $reflection = new ReflectionMethod($class, $method);
            } catch (\ReflectionException) {
                continue;
            }

            $file = $reflection->getFileName();
            if (! $file) {
                continue;
            }

            if (! isset($sources[$file])) {
                $sources[$file] = file($file) ?: [];
            }

            $start = $reflection->getStartLine();
            $end = $reflection->getEndLine();
            $body = implode('', array_slice($sources[$file], $start - 1, $end - $start + 1));

            if (str_contains($body, 'return response()->json')) {
                $uris[] = $entry['uri'];
            }
        }

        return array_values(array_unique($uris));
    }
}
