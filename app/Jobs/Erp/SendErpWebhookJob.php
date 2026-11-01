<?php

namespace App\Jobs\Erp;

use App\Models\ErpApiToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers a single ERP webhook to one token's configured endpoint.
 *
 * Signing: ERP stores api_secret in plaintext and hashes it once to derive
 * the HMAC key. 6ammart stores it already hashed (`sha256(plaintext)`), so
 * passing $token->api_secret straight into hash_hmac yields the same key
 * the ERP middleware verifies against. No additional shared secret is
 * needed and rotating the token rotates the webhook signature too.
 */
class SendErpWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [10, 30, 120, 300];

    public function __construct(
        public int $tokenId,
        public string $event,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $token = ErpApiToken::query()->withWebhook()->find($this->tokenId);
        if (! $token) {
            return;
        }

        $url = rtrim($token->webhook_url, '/');
        $body = json_encode([
            'product' => '6ammart',
            'event' => $this->event,
            'data' => $this->payload,
        ], JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $body, $token->api_secret);

        try {
            $response = Http::withHeaders([
                'X-Webhook-Key' => $token->api_key,
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event' => $this->event,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(15)
                ->connectTimeout(5)
                ->withBody($body, 'application/json')
                ->post($url);

            if ($response->failed()) {
                Log::warning('ERP webhook delivery non-2xx', [
                    'token_id' => $token->id,
                    'event' => $this->event,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                if ($response->serverError()) {
                    $this->release($this->backoff[$this->attempts() - 1] ?? 300);

                    return;
                }
            }

            $token->forceFill(['webhook_last_dispatched_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning('ERP webhook delivery threw', [
                'token_id' => $token->id,
                'event' => $this->event,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
