<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\Cache\ApiCache;

class BaseApiController extends Controller
{
    protected const DEMO_LIMIT = 10;

    protected function responseFormatter($config, $content = null, $errors = []): JsonResponse
    {
        $config = (array) $config;

        return response()->json(
            ApiEnvelope::make($config, $content, $errors ?? []),
            $config['http_response_code'] ?? 200,
            [],
            JSON_PRESERVE_ZERO_FRACTION
        );
    }

    protected function perPage(Request $request): int
    {
        $perPage = (int) ($request->input('limit') ?: config('default_pagination'));

        return $perPage > 0 ? $perPage : (int) config('default_pagination');
    }

    protected function page(Request $request): int
    {
        return max(1, (int) ($request->input('offset') ?: 1));
    }

    protected function pagedResponse(mixed $paginator, string $resource): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), [
            'data' => $resource::collection($paginator),
            'pagination' => $this->paginateFormatter($paginator),
        ]);
    }

    protected function errorResponse(array $config, mixed $message, mixed $code = 'error'): JsonResponse
    {
        return $this->responseFormatter($config, errors: [['code' => $code, 'message' => $message]]);
    }

    protected function statusConfig(int $status): array
    {
        return match ($status) {
            200 => config('response.default_200'),
            201 => config('response.default_store_201'),
            400 => config('response.bad_request_400'),
            401 => config('response.unauthorized_401'),
            403 => config('response.forbidden_403'),
            404 => config('response.default_404'),
            405 => config('response.method_not_allowed_405'),
            406 => config('response.not_acceptable_406'),
            409 => config('response.already_exists_409'),
            422 => config('response.unprocessable_entity_422'),
            default => config('response.default_500'),
        };
    }

    protected function serviceResponse(array $result, ?array $success = null): JsonResponse
    {
        $status = (int) ($result['status_code'] ?? 200);
        $config = $status < 400 ? ($success ?? $this->statusConfig($status)) : $this->statusConfig($status);

        if (isset($result['message'])) {
            $config = ['message' => $result['message']] + $config;
        }

        return $status < 400
            ? $this->responseFormatter($config)
            : $this->responseFormatter($config, errors: [['code' => $result['code'] ?? 'error', 'message' => $result['message'] ?? null]]);
    }

    protected function paginateFormatter($collection): array
    {
        return [
            'current_page' => $collection->currentPage(),
            'last_page' => $collection->lastPage(),
            'per_page' => $collection->perPage(),
            'total' => $collection->total(),
            'from' => $collection->firstItem(),
            'to' => $collection->lastItem(),
        ];
    }

    protected function emptyPaginator(int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, max(1, $perPage), max(1, $page));
    }

    protected function demoLimitReached(string $cachePrefix = 'restricted_ip_', int $limit = self::DEMO_LIMIT): bool
    {
        if (getEnvMode() !== 'demo') {
            return false;
        }

        $cacheKey = $cachePrefix.(request()->header('x-forwarded-for') ?: request()->ip());
        $hits = (int) ApiCache::get('demo_throttle', $cacheKey, 0);

        if ($hits >= $limit) {
            return true;
        }

        ApiCache::put('demo_throttle', $cacheKey, $hits + 1);

        return false;
    }
}
