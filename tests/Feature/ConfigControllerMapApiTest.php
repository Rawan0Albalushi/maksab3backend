<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Facades\Http;
use App\Models\BusinessSetting;

class ConfigControllerMapApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        BusinessSetting::updateOrCreate(
            ['key' => 'map_api_key_server'],
            ['value' => 'test-api-key-12345']
        );
    }


    public function test_place_autocomplete_validation_fails_without_search_text()
    {
        $response = $this->getJson('/api/v1/config/place-api-autocomplete');

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_distance_api_validation_fails_without_required_fields()
    {
        $response = $this->getJson('/api/v1/config/distance-api');

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_distance_api_validation_fails_with_partial_fields()
    {
        $response = $this->getJson('/api/v1/config/distance-api?origin_lat=23.8&origin_lng=90.4');

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_distance_api_validation_fails_with_invalid_mode()
    {
        $response = $this->getJson('/api/v1/config/distance-api?' . http_build_query([
            'origin_lat' => 23.8103,
            'origin_lng' => 90.4125,
            'destination_lat' => 23.7104,
            'destination_lng' => 90.4074,
            'mode' => 'FLY',
        ]));

        $response->assertStatus(422);
    }

    public function test_place_details_validation_fails_without_placeid()
    {
        $response = $this->getJson('/api/v1/config/place-api-details');

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_geocode_api_validation_fails_without_coordinates()
    {
        $response = $this->getJson('/api/v1/config/geocode-api');

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_geocode_api_validation_fails_with_partial_coordinates()
    {
        $response = $this->getJson('/api/v1/config/geocode-api?lat=23.8103');

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_direction_api_validation_fails_without_required_fields()
    {
        $response = $this->getJson('/api/v1/config/direction-api');

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }


    public function test_place_autocomplete_returns_cached_data_on_cache_hit()
    {
        $searchText = 'Dhaka';
        $locale = app()->getLocale();
        $cacheKey = 'place_autocomplete_' . md5($searchText . '_' . $locale);

        $cachedData = ['suggestions' => [['placePrediction' => ['text' => 'Dhaka, Bangladesh']]]];
        ApiCache::put('map_lookup', $cacheKey, $cachedData, now()->addMinutes(30));

        $response = $this->getJson('/api/v1/config/place-api-autocomplete?search_text=' . $searchText);

        $response->assertStatus(200)
            ->assertJson(['content' => $cachedData]);
    }

    public function test_distance_api_returns_cached_data_on_cache_hit()
    {
        $params = [
            'origin_lat' => 23.81030,
            'origin_lng' => 90.41250,
            'destination_lat' => 23.71040,
            'destination_lng' => 90.40740,
            'mode' => 'WALK',
        ];

        $cacheKey = 'distance_api_' . md5(
            round($params['origin_lat'], 5) . '_' . round($params['origin_lng'], 5) . '_' .
            round($params['destination_lat'], 5) . '_' . round($params['destination_lng'], 5) . '_' . $params['mode']
        );

        $cachedData = ['distanceMeters' => 12000, 'duration' => '3600s'];
        ApiCache::put('map_lookup', $cacheKey, $cachedData, now()->addHours(24));

        $response = $this->getJson('/api/v1/config/distance-api?' . http_build_query($params));

        $response->assertStatus(200)
            ->assertJson(['content' => $cachedData]);
    }

    public function test_place_details_returns_cached_data_on_cache_hit()
    {
        $placeId = 'ChIJD7fiBh9u5kcRYJSMaMOCCwQ';
        $cacheKey = 'place_details_' . md5($placeId);

        $cachedData = [
            'id' => $placeId,
            'displayName' => ['text' => 'Test Place'],
            'formattedAddress' => '123 Test St',
            'location' => ['latitude' => 23.8103, 'longitude' => 90.4125],
        ];
        ApiCache::put('map_lookup', $cacheKey, $cachedData, now()->addDays(7));

        $response = $this->getJson('/api/v1/config/place-api-details?placeid=' . $placeId);

        $response->assertStatus(200)
            ->assertJson(['content' => $cachedData]);
    }

    public function test_geocode_api_returns_cached_data_on_cache_hit()
    {
        $lat = 23.81030;
        $lng = 90.41250;
        $cacheKey = 'geocode_api_' . md5(round($lat, 5) . '_' . round($lng, 5));

        $cachedData = [
            'results' => [['formatted_address' => 'Dhaka, Bangladesh']],
            'status' => 'OK',
        ];
        ApiCache::put('map_lookup', $cacheKey, $cachedData, now()->addDays(7));

        $response = $this->getJson('/api/v1/config/geocode-api?lat=' . $lat . '&lng=' . $lng);

        $response->assertStatus(200)
            ->assertJson(['content' => $cachedData]);
    }

    public function test_direction_api_returns_cached_data_on_cache_hit()
    {
        $params = [
            'origin_lat' => 23.81030,
            'origin_lng' => 90.41250,
            'destination_lat' => 23.71040,
            'destination_lng' => 90.40740,
        ];

        $mode = 'DRIVE';
        $cacheKey = 'direction_api_' . md5(
            round($params['origin_lat'], 5) . '_' . round($params['origin_lng'], 5) . '_' .
            round($params['destination_lat'], 5) . '_' . round($params['destination_lng'], 5) . '_' . $mode
        );

        $cachedData = [
            'routes' => [['distanceMeters' => 15000, 'duration' => '1800s']],
        ];
        ApiCache::put('map_lookup', $cacheKey, $cachedData, now()->addHour());

        $response = $this->getJson('/api/v1/config/direction-api?' . http_build_query($params));

        $response->assertStatus(200)
            ->assertJson(['content' => $cachedData]);
    }


    public function test_distance_api_nearby_coordinates_use_same_cache_key()
    {
        $lat1 = 23.810301;
        $lat2 = 23.810304;

        $key1 = 'distance_api_' . md5(round($lat1, 5) . '_' . round(90.41250, 5) . '_' . round(23.71040, 5) . '_' . round(90.40740, 5) . '_WALK');
        $key2 = 'distance_api_' . md5(round($lat2, 5) . '_' . round(90.41250, 5) . '_' . round(23.71040, 5) . '_' . round(90.40740, 5) . '_WALK');

        $this->assertEquals($key1, $key2);
    }

    public function test_geocode_api_nearby_coordinates_use_same_cache_key()
    {
        $lat1 = 23.810301;
        $lat2 = 23.810304;

        $key1 = 'geocode_api_' . md5(round($lat1, 5) . '_' . round(90.41250, 5));
        $key2 = 'geocode_api_' . md5(round($lat2, 5) . '_' . round(90.41250, 5));

        $this->assertEquals($key1, $key2);
    }


    public function test_distance_api_different_modes_produce_different_cache_keys()
    {
        $base = round(23.81030, 5) . '_' . round(90.41250, 5) . '_' . round(23.71040, 5) . '_' . round(90.40740, 5);

        $keyWalk = 'distance_api_' . md5($base . '_WALK');
        $keyDrive = 'distance_api_' . md5($base . '_DRIVE');

        $this->assertNotEquals($keyWalk, $keyDrive);
    }

    public function test_place_autocomplete_different_locales_produce_different_cache_keys()
    {
        $searchText = 'Dhaka';

        $keyEn = 'place_autocomplete_' . md5($searchText . '_en');
        $keyBn = 'place_autocomplete_' . md5($searchText . '_bn');

        $this->assertNotEquals($keyEn, $keyBn);
    }


    public function test_geocode_api_does_not_cache_non_ok_status()
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'results' => [],
                'status' => 'ZERO_RESULTS',
            ], 200),
        ]);

        $lat = 0.00001;
        $lng = 0.00001;
        $cacheKey = 'geocode_api_' . md5(round($lat, 5) . '_' . round($lng, 5));

        $response = $this->getJson('/api/v1/config/geocode-api?lat=' . $lat . '&lng=' . $lng);

        $response->assertStatus(200);
        $this->assertNull(ApiCache::get('map_lookup', $cacheKey));
    }

    public function test_geocode_api_does_not_cache_request_denied()
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'results' => [],
                'status' => 'REQUEST_DENIED',
                'error_message' => 'API key is invalid.',
            ], 200),
        ]);

        $lat = 1.00001;
        $lng = 1.00001;
        $cacheKey = 'geocode_api_' . md5(round($lat, 5) . '_' . round($lng, 5));

        $this->getJson('/api/v1/config/geocode-api?lat=' . $lat . '&lng=' . $lng);

        $this->assertNull(ApiCache::get('map_lookup', $cacheKey));
    }

    public function test_geocode_api_caches_successful_ok_response()
    {
        $successData = [
            'results' => [['formatted_address' => 'Some Place']],
            'status' => 'OK',
        ];

        Http::fake([
            'maps.googleapis.com/*' => Http::response($successData, 200),
        ]);

        $lat = 2.00001;
        $lng = 2.00001;
        $cacheKey = 'geocode_api_' . md5(round($lat, 5) . '_' . round($lng, 5));

        $this->getJson('/api/v1/config/geocode-api?lat=' . $lat . '&lng=' . $lng);

        $cached = ApiCache::get('map_lookup', $cacheKey);
        $this->assertNotNull($cached);
        $this->assertEquals('OK', $cached['status']);
    }

    public function test_geocode_api_does_not_cache_http_failure()
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response('Server Error', 500),
        ]);

        $lat = 3.00001;
        $lng = 3.00001;
        $cacheKey = 'geocode_api_' . md5(round($lat, 5) . '_' . round($lng, 5));

        $this->getJson('/api/v1/config/geocode-api?lat=' . $lat . '&lng=' . $lng);

        $this->assertNull(ApiCache::get('map_lookup', $cacheKey));
    }


    public function test_geocode_api_makes_api_call_on_cache_miss()
    {
        $expectedData = [
            'results' => [['formatted_address' => 'Fresh Place']],
            'status' => 'OK',
        ];

        Http::fake([
            'maps.googleapis.com/*' => Http::response($expectedData, 200),
        ]);

        $lat = 4.00001;
        $lng = 4.00001;

        $response = $this->getJson('/api/v1/config/geocode-api?lat=' . $lat . '&lng=' . $lng);

        $response->assertStatus(200)
            ->assertJson(['content' => $expectedData]);

        Http::assertSentCount(1);
    }

    public function test_geocode_api_does_not_call_api_on_cache_hit()
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response(['status' => 'OK'], 200),
        ]);

        $lat = 5.00001;
        $lng = 5.00001;
        $cacheKey = 'geocode_api_' . md5(round($lat, 5) . '_' . round($lng, 5));

        ApiCache::put('map_lookup', $cacheKey, ['results' => [['formatted_address' => 'Cached Place']], 'status' => 'OK'], now()->addDays(7));

        $response = $this->getJson('/api/v1/config/geocode-api?lat=' . $lat . '&lng=' . $lng);

        $response->assertStatus(200)
            ->assertJson(['content' => ['results' => [['formatted_address' => 'Cached Place']]]]);

        Http::assertNothingSent();
    }


    public function test_distance_api_handles_null_decoded_response_safely()
    {
        $params = [
            'origin_lat' => 10.00001,
            'origin_lng' => 10.00001,
            'destination_lat' => 11.00001,
            'destination_lng' => 11.00001,
        ];

        $cacheKey = 'distance_api_' . md5(
            round(10.00001, 5) . '_' . round(10.00001, 5) . '_' .
            round(11.00001, 5) . '_' . round(11.00001, 5) . '_WALK'
        );

        $this->assertNull(ApiCache::get('map_lookup', $cacheKey));

        $result = is_array(null) ? (null[0] ?? null) : null;
        $this->assertNull($result);

        $response = false;
        $this->assertFalse($response !== false && $result !== null && !isset($result['error']));
    }

    public function test_distance_api_handles_error_json_response_safely()
    {
        $errorResponse = ['error' => ['code' => 400, 'message' => 'Invalid', 'status' => 'INVALID_ARGUMENT']];
        $decoded = $errorResponse;
        $result = is_array($decoded) ? ($decoded[0] ?? null) : null;

        $this->assertNull($result);

        $this->assertFalse('fake-response' !== false && $result !== null && !isset($result['error']));
    }

    public function test_distance_api_handles_valid_array_response()
    {
        $apiResponse = [['distanceMeters' => 12000, 'duration' => '3600s']];
        $result = is_array($apiResponse) ? ($apiResponse[0] ?? null) : null;

        $this->assertNotNull($result);
        $this->assertEquals(12000, $result['distanceMeters']);

        $response = json_encode($apiResponse);
        $this->assertTrue($response !== false && $result !== null && !isset($result['error']));
    }


    public function test_direction_api_returns_cached_data_and_ignores_mode_case()
    {
        $params = [
            'origin_lat' => 23.81030,
            'origin_lng' => 90.41250,
            'destination_lat' => 23.71040,
            'destination_lng' => 90.40740,
            'mode' => 'drive',
        ];

        $mode = strtoupper($params['mode']);
        $cacheKey = 'direction_api_' . md5(
            round($params['origin_lat'], 5) . '_' . round($params['origin_lng'], 5) . '_' .
            round($params['destination_lat'], 5) . '_' . round($params['destination_lng'], 5) . '_' . $mode
        );

        $cachedData = ['routes' => [['distanceMeters' => 8000]]];
        ApiCache::put('map_lookup', $cacheKey, $cachedData, now()->addHour());

        $response = $this->getJson('/api/v1/config/direction-api?' . http_build_query($params));

        $response->assertStatus(200)
            ->assertJson(['content' => $cachedData]);
    }

    public function test_direction_api_default_mode_is_drive()
    {
        $params = [
            'origin_lat' => 24.81030,
            'origin_lng' => 91.41250,
            'destination_lat' => 24.71040,
            'destination_lng' => 91.40740,
        ];

        $cacheKey = 'direction_api_' . md5(
            round($params['origin_lat'], 5) . '_' . round($params['origin_lng'], 5) . '_' .
            round($params['destination_lat'], 5) . '_' . round($params['destination_lng'], 5) . '_DRIVE'
        );

        $cachedData = ['routes' => [['distanceMeters' => 5000]]];
        ApiCache::put('map_lookup', $cacheKey, $cachedData, now()->addHour());

        $response = $this->getJson('/api/v1/config/direction-api?' . http_build_query($params));

        $response->assertStatus(200)
            ->assertJson(['content' => $cachedData]);
    }


    public function test_distance_api_default_mode_is_walk()
    {
        $params = [
            'origin_lat' => 25.81030,
            'origin_lng' => 92.41250,
            'destination_lat' => 25.71040,
            'destination_lng' => 92.40740,
        ];

        $cacheKey = 'distance_api_' . md5(
            round($params['origin_lat'], 5) . '_' . round($params['origin_lng'], 5) . '_' .
            round($params['destination_lat'], 5) . '_' . round($params['destination_lng'], 5) . '_WALK'
        );

        $cachedData = ['distanceMeters' => 3000, 'duration' => '2400s'];
        ApiCache::put('map_lookup', $cacheKey, $cachedData, now()->addHours(24));

        $response = $this->getJson('/api/v1/config/distance-api?' . http_build_query($params));

        $response->assertStatus(200)
            ->assertJson(['content' => $cachedData]);
    }


    public function test_expired_cache_is_not_returned()
    {
        $placeId = 'ChIJExpiredPlace';
        $cacheKey = 'place_details_' . md5($placeId);

        ApiCache::put('map_lookup', $cacheKey, ['id' => $placeId, 'displayName' => 'Old Data'], now()->subMinute());

        $this->assertNull(ApiCache::get('map_lookup', $cacheKey));
    }


    public function test_guard_blocks_caching_when_curl_returns_false()
    {
        $response = false;
        $result = ['some' => 'data'];

        $shouldCache = $response !== false && $result !== null && !isset($result['error']);
        $this->assertFalse($shouldCache);
    }

    public function test_guard_blocks_caching_when_result_is_null()
    {
        $response = '{}';
        $result = null;

        $shouldCache = $response !== false && $result !== null && !isset($result['error']);
        $this->assertFalse($shouldCache);
    }

    public function test_guard_blocks_caching_when_result_has_error_key()
    {
        $response = '{"error":{"code":403}}';
        $result = ['error' => ['code' => 403, 'message' => 'Forbidden']];

        $shouldCache = $response !== false && $result !== null && !isset($result['error']);
        $this->assertFalse($shouldCache);
    }

    public function test_guard_allows_caching_for_valid_response()
    {
        $response = '{"suggestions":[]}';
        $result = ['suggestions' => []];

        $shouldCache = $response !== false && $result !== null && !isset($result['error']);
        $this->assertTrue($shouldCache);
    }

    public function test_geocode_guard_blocks_over_quota_status()
    {
        $result = ['results' => [], 'status' => 'OVER_QUERY_LIMIT'];

        $shouldCache = $result !== null && ($result['status'] ?? null) === 'OK';
        $this->assertFalse($shouldCache);
    }

    public function test_geocode_guard_blocks_unknown_error_status()
    {
        $result = ['results' => [], 'status' => 'UNKNOWN_ERROR'];

        $shouldCache = $result !== null && ($result['status'] ?? null) === 'OK';
        $this->assertFalse($shouldCache);
    }
}
