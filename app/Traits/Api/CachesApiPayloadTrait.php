<?php

namespace App\Traits\Api;

use App\Support\Cache\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait CachesApiPayloadTrait
{
    protected function cachedPayload(string $group, Request $request, array $extra, callable $builder, bool $perUser = false): mixed
    {
        if (! $this->payloadCacheable($request)) {
            return $builder();
        }

        return ApiCache::remember(
            $group,
            $this->payloadContext($request, $extra, $perUser),
            fn () => $this->normalizePayload($builder())
        );
    }

    protected function cachedJson(string $group, Request $request, array $extra, callable $builder, bool $perUser = false): JsonResponse
    {
        if (! $this->payloadCacheable($request)) {
            return $builder();
        }

        $context = $this->payloadContext($request, $extra, $perUser);
        $cached = ApiCache::get($group, $context);

        if (is_array($cached) && array_key_exists('data', $cached)) {
            return response()->json($cached['data'], $cached['status'] ?? 200);
        }

        $response = $builder();

        if ($response->getStatusCode() === 200) {
            ApiCache::put($group, $context, ['status' => 200, 'data' => $response->getData(true)]);
        }

        return $response;
    }

    protected function payloadContext(Request $request, array $extra = [], bool $perUser = false): array
    {
        return [
            'zone' => $request->header('zoneId'),
            'module' => config('module.current_module_data')['id'] ?? null,
            'module_type' => config('module.current_module_data')['module_type'] ?? null,
            'locale' => app()->getLocale(),
            'identity' => $perUser ? 'u:'.(auth('api')->id() ?: 'guest') : $this->payloadIdentity(),
            'page' => $this->page($request),
            'per_page' => $this->perPage($request),
            'extra' => $this->withoutIdentityKeys($extra),
        ];
    }

    protected function normalizePayload(mixed $payload): mixed
    {
        return json_decode(json_encode($payload), true);
    }

    protected function withoutIdentityKeys(array $extra): array
    {
        unset($extra['user_id'], $extra['customer_id'], $extra['guest_id']);

        return $extra;
    }

    protected function payloadIdentity(): string
    {
        if (! $this->personalizationActive()) {
            return 'shared';
        }

        return (string) (auth('api')->id() ?: 'guest');
    }

    protected function personalizationActive(): bool
    {
        if (! class_exists(\Modules\AI\app\Core\AiModule::class)) {
            return false;
        }

        return \Modules\AI\app\Core\AiModule::isPersonalizationActive();
    }

    protected function payloadCacheable(Request $request): bool
    {
        return $this->page($request) <= (int) config('api_cache.max_cached_page', 3);
    }

    protected function geoBucket(Request $request): array
    {
        $latitude = $request->header('latitude');
        $longitude = $request->header('longitude');

        return [
            is_numeric($latitude) ? round((float) $latitude, 3) : null,
            is_numeric($longitude) ? round((float) $longitude, 3) : null,
        ];
    }
}
