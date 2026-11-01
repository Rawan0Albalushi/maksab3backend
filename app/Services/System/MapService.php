<?php

namespace App\Services\System;

use App\Services\BaseService;
use Closure;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Facades\Http;

class MapService extends BaseService
{
    private const AUTOCOMPLETE_URL = 'https://places.googleapis.com/v1/places:autocomplete';

    private const PLACE_DETAILS_URL = 'https://places.googleapis.com/v1/places/';

    private const GEOCODE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    private const DISTANCE_MATRIX_URL = 'https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix';

    private const COMPUTE_ROUTES_URL = 'https://routes.googleapis.com/directions/v2:computeRoutes';

    private ?string $apiKey = null;

    public function __construct(
        private readonly ?Closure $apiKeyResolver = null
    ) {
    }

    public function searchPlaces(string $searchText, string $locale): mixed
    {
        $cacheKey = 'place_autocomplete_' . md5($searchText . '_' . $locale);

        return $this->remember($cacheKey, now()->addMinutes(30), fn () => $this->post(
            self::AUTOCOMPLETE_URL,
            ['input' => $searchText, 'languageCode' => $locale],
        ));
    }

    public function findPlace(string $placeId): mixed
    {
        $cacheKey = 'place_details_' . md5($placeId);

        return $this->remember($cacheKey, now()->addDays(7), fn () => $this->get(
            self::PLACE_DETAILS_URL . $placeId,
            ['X-Goog-FieldMask: id,displayName,formattedAddress,location'],
        ));
    }

    public function resolveCoordinates(mixed $latitude, mixed $longitude): mixed
    {
        $cacheKey = 'geocode_api_' . md5(round($latitude, 5) . '_' . round($longitude, 5));

        $cached = $this->cached($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $response = Http::get(self::GEOCODE_URL . '?latlng=' . $latitude . ',' . $longitude . '&key=' . $this->apiKey());
        $result = $response->json();

        if ($response->successful() && $result !== null && ($result['status'] ?? null) === 'OK') {
            $this->store($cacheKey, $result, now()->addDays(7));
        }

        return $result;
    }

    public function routeMatrix(array $origin, array $destination, string $mode): mixed
    {
        $cacheKey = 'distance_api_' . $this->routeHash($origin, $destination, $mode);

        return $this->remember($cacheKey, now()->addHours(24), function () use ($origin, $destination, $mode) {
            $decoded = $this->post(
                self::DISTANCE_MATRIX_URL,
                [
                    'origins' => [['waypoint' => ['location' => ['latLng' => $this->latLng($origin)]]]],
                    'destinations' => [['waypoint' => ['location' => ['latLng' => $this->latLng($destination)]]]],
                    'travelMode' => $mode,
                ],
                ['X-Goog-FieldMask: duration,distanceMeters,localizedValues'],
            );

            return is_array($decoded) ? ($decoded[0] ?? null) : null;
        });
    }

    public function computeRoute(array $origin, array $destination, string $mode): mixed
    {
        $cacheKey = 'direction_api_' . $this->routeHash($origin, $destination, $mode);

        $cached = $this->cached($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->post(
            self::COMPUTE_ROUTES_URL,
            [
                'origin' => ['location' => ['latLng' => $this->latLng($origin)]],
                'destination' => ['location' => ['latLng' => $this->latLng($destination)]],
                'travelMode' => $mode,
                'routingPreference' => 'TRAFFIC_AWARE',
            ],
            ['X-Goog-FieldMask: routes.duration,routes.distanceMeters,routes.polyline.encodedPolyline'],
            failOnError: true,
        );

        if (is_array($result) && array_key_exists('error', $result)) {
            return $result;
        }

        if ($result !== null) {
            $this->store($cacheKey, $result, now()->addHour());
        }

        return $result;
    }

    private function remember(string $cacheKey, mixed $expiresAt, callable $resolver): mixed
    {
        $cached = $this->cached($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $resolver();

        if ($result !== null && ! (is_array($result) && isset($result['error']))) {
            $this->store($cacheKey, $result, $expiresAt);
        }

        return $result;
    }

    private function cached(string $cacheKey): mixed
    {
        try {
            return ApiCache::get('map_lookup', $cacheKey);
        } catch (\Exception $exception) {
            return null;
        }
    }

    private function store(string $cacheKey, mixed $value, mixed $expiresAt): void
    {
        try {
            ApiCache::put('map_lookup', $cacheKey, $value, $expiresAt);
        } catch (\Exception $exception) {
        }
    }

    private function post(string $url, array $payload, array $extraHeaders = [], bool $failOnError = false): mixed
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_HTTPHEADER, array_merge($this->headers(), $extraHeaders));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($payload));

        if ($failOnError) {
            curl_setopt($handle, CURLOPT_FAILONERROR, true);
        }

        $response = curl_exec($handle);
        $error = curl_error($handle);
        curl_close($handle);

        if ($failOnError && $error) {
            return ['error' => $error];
        }

        return $response === false ? null : json_decode($response, true);
    }

    private function get(string $url, array $extraHeaders = []): mixed
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HTTPHEADER, array_merge($this->headers(), $extraHeaders));

        $response = curl_exec($handle);
        curl_close($handle);

        return $response === false ? null : json_decode($response, true);
    }

    private function headers(): array
    {
        return [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $this->apiKey(),
        ];
    }

    private function latLng(array $point): array
    {
        return ['latitude' => $point['lat'], 'longitude' => $point['lng']];
    }

    private function routeHash(array $origin, array $destination, string $mode): string
    {
        return md5(
            round($origin['lat'], 5) . '_' . round($origin['lng'], 5) . '_' .
            round($destination['lat'], 5) . '_' . round($destination['lng'], 5) . '_' . $mode
        );
    }

    private function apiKey(): ?string
    {
        return $this->apiKey ??= ($this->apiKeyResolver)();
    }

}
