<?php

namespace App\Support\Notification\Fcm;

use App\Support\Notification\NotificationConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmClient
{
    private const DEAD_TOKEN_CODES = ['UNREGISTERED', 'NOT_FOUND', 'SENDER_ID_MISMATCH'];

    private const RETRYABLE_CODES = ['UNAVAILABLE', 'INTERNAL', 'QUOTA_EXCEEDED', 'RESOURCE_EXHAUSTED'];


    public function sendRaw(?array $payload, array $context = []): FcmResult
    {
        if (! is_array($payload)) {
            return FcmResult::skipped('empty_payload');
        }

        $target = data_get($payload, 'message.token') ?? data_get($payload, 'message.topic');

        return $this->post($payload, $target, $context);
    }


    public function sendRawQuietly(?array $payload, array $context = []): FcmResult
    {
        return $this->quietly(fn () => $this->sendRaw($payload, $context));
    }

    private function quietly(callable $send): FcmResult
    {
        try {
            return $send();
        } catch (FcmException $exception) {
            return FcmResult::failed($exception->status ?? 0, null, $exception->getMessage());
        } catch (\Throwable $exception) {
            return FcmResult::failed(0, null, $exception->getMessage());
        }
    }

    private function post(array $payload, ?string $target, array $context): FcmResult
    {
        $key = FcmCredentials::serviceAccount();
        $url = FcmCredentials::endpoint($key);

        if (! $url) {
            return FcmResult::skipped('no_service_account');
        }

        if (data_get($payload, 'message.token') !== null
            && ! FcmTokenResolver::isUsable(data_get($payload, 'message.token'))) {
            $this->log('debug', 'fcm.skipped', $context + ['reason' => 'unusable_token']);

            return FcmResult::skipped('unusable_token');
        }

        $accessToken = FcmCredentials::accessToken($key);

        if (! $accessToken) {
            $this->log('error', 'fcm.no_access_token', $context);

            return FcmResult::skipped('no_access_token');
        }

        $startedAt = microtime(true);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ])
                ->connectTimeout((int) config('notification.fcm.connect_timeout', 5))
                ->timeout((int) config('notification.fcm.timeout', 10))
                ->post($url, $payload);
        } catch (\Throwable $exception) {
            $this->log('warning', 'fcm.transport_error', $context + [
                'target' => $this->maskTarget($target),
                'error' => $exception->getMessage(),
            ]);

            throw new FcmException($exception->getMessage());
        }

        $duration = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->successful()) {
            return FcmResult::ok($response->json('name'));
        }

        $status = $response->status();
        $errorCode = (string) ($response->json('error.details.0.errorCode') ?? $response->json('error.status') ?? '');
        $errorMessage = (string) ($response->json('error.message') ?? $response->body());

        if ($status === 401 || $status === 403) {
            FcmCredentials::forget($key);
        }

        $context += [
            'target' => $this->maskTarget($target),
            'status' => $status,
            'error_code' => $errorCode,
            'error' => $errorMessage,
            'duration_ms' => $duration,
        ];

        if ($status >= 500 || in_array($errorCode, self::RETRYABLE_CODES, true) || $status === 429) {
            $this->log('warning', 'fcm.retryable_failure', $context);

            throw new FcmException($errorMessage, $status);
        }

        $tokenIsDead = data_get($payload, 'message.token') !== null
            && in_array($errorCode, self::DEAD_TOKEN_CODES, true);

        $this->log($tokenIsDead ? 'info' : 'error', $tokenIsDead ? 'fcm.dead_token' : 'fcm.failed', $context);

        return FcmResult::failed($status, $errorCode ?: null, $errorMessage, $tokenIsDead);
    }

    private function maskTarget(?string $target): ?string
    {
        if ($target === null) {
            return null;
        }

        return strlen($target) > 16 ? substr($target, 0, 8).'…'.substr($target, -4) : $target;
    }

    private function log(string $level, string $event, array $context): void
    {
        Log::channel(NotificationConfig::logChannel())->{$level}($event, $context);
    }
}
