<?php

namespace App\Traits\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait ModuleDelegationTrait
{
    use ApiRequestContextTrait;

    protected function serviceFilters(Request $request, array $extra = []): array
    {
        $this->applyZoneIds($request);

        return array_merge([
            'zone_ids' => json_decode($request->header('zoneId'), true) ?? [],
            'module_id' => config('module.current_module_data')['id'] ?? null,
            'longitude' => is_numeric($request->header('longitude')) ? (float) $request->header('longitude') : 0.0,
            'latitude' => is_numeric($request->header('latitude')) ? (float) $request->header('latitude') : 0.0,
            'limit' => $request->input('limit'),
            'offset' => $request->input('offset'),
            'name' => $request->input('name'),
            'search' => $request->query('search'),
            'featured' => $request->query('featured'),
            'type' => $request->query('type', 'all'),
            'store_type' => $request->query('store_type', 'all'),
            'store_id' => $request->input('store_id'),
            'category_ids' => $request->input('category_ids'),
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price'),
            'price' => $request->input('price'),
            'rating' => $request->input('rating'),
            'user' => auth('api')->user(),
        ], $extra);
    }

    protected function serviceResult(mixed $result): JsonResponse
    {
        if (is_array($result) && isset($result['error'])) {
            $error = $result['error'];

            if (isset($error['messages'])) {
                return $this->responseFormatter(
                    ['message' => $error['message']] + config('response.unprocessable_entity_422'),
                    errors: $error['messages'],
                );
            }

            return $this->responseFormatter(
                config('response.'.($error['status'] ?? 'forbidden_403')),
                errors: [['code' => $error['code'] ?? 'error', 'message' => $error['message'] ?? null]],
            );
        }

        return $this->responseFormatter(config('response.default_200'), $result);
    }
}
