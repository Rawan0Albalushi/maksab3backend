<?php

namespace App\Support\Notification\Fcm;

use App\Support\Cache\ApiCache;
use Illuminate\Support\Facades\Http;
use App\Services\System\BusinessSettingService;

class FcmCredentials
{
    public static function serviceAccount(): array
    {
        return (array) app(BusinessSettingService::class)->value('push_notification_service_file_content');
    }

    public static function projectId(?array $key = null): ?string
    {
        return data_get($key ?? self::serviceAccount(), 'project_id');
    }

    public static function endpoint(?array $key = null): ?string
    {
        $projectId = self::projectId($key);

        return $projectId ? 'https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send' : null;
    }

    public static function accessToken(array $key): ?string
    {
        $clientEmail = data_get($key, 'client_email');
        $privateKey = data_get($key, 'private_key');

        if (! $clientEmail || ! $privateKey) {
            return null;
        }

        return ApiCache::remember(
            'fcm_token',
            md5($clientEmail.'|'.$privateKey),
            fn () => self::requestAccessToken($clientEmail, $privateKey),
        );
    }

    private static function requestAccessToken(string $clientEmail, string $privateKey): ?string
    {
        $jwtToken = [
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $jwtHeader = self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtPayload = self::base64Url(json_encode($jwtToken));
        $unsignedJwt = $jwtHeader.'.'.$jwtPayload;

        openssl_sign($unsignedJwt, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $jwt = $unsignedJwt.'.'.self::base64Url($signature);

        try {
            $response = Http::asForm()
                ->connectTimeout((int) config('notification.fcm.connect_timeout', 5))
                ->timeout((int) config('notification.fcm.timeout', 10))
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);
        } catch (\Throwable) {
            return null;
        }

        return $response->json('access_token');
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function forget(array $key): void
    {
        $clientEmail = data_get($key, 'client_email');
        $privateKey = data_get($key, 'private_key');

        if ($clientEmail && $privateKey) {
            ApiCache::forget('fcm_token', md5($clientEmail.'|'.$privateKey));
        }
    }
}
