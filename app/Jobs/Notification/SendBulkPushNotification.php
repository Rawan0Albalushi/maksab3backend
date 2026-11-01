<?php

namespace App\Jobs\Notification;

use App\Support\Notification\Fcm\DeadTokenPruner;
use App\Support\Notification\Fcm\FcmClient;
use App\Support\Notification\Fcm\FcmMessage;
use App\Support\Notification\NotificationConfig;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBulkPushNotification implements ShouldQueue, ShouldQueueAfterCommit
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public array $backoff;

    public int $timeout;

    public function __construct(
        public readonly array $tokens,
        public readonly array $payload,
        public readonly array $context = [],
    ) {
        $this->tries = NotificationConfig::tries();
        $this->backoff = NotificationConfig::backoff();
        $this->timeout = NotificationConfig::timeout();

        $this->onConnection(NotificationConfig::connection());
        $this->onQueue(NotificationConfig::queueFor('bulk'));
    }

    public function handle(FcmClient $client): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $sent = 0;
        $pruned = 0;

        foreach ($this->tokens as $token) {
            $payload = $this->payload;
            $payload['message']['token'] = $token;

            $result = $client->sendRawQuietly($payload, $this->context);

            if ($result->sent) {
                $sent++;
            }

            if ($result->tokenIsDead) {
                $pruned += DeadTokenPruner::prune($token);
            }
        }

        $attempted = count($this->tokens);

        if ($sent < $attempted) {
            Log::channel(NotificationConfig::logChannel())->warning('push.bulk_chunk_failures', $this->context + [
                'attempted' => $attempted,
                'sent' => $sent,
                'failed' => $attempted - $sent,
                'pruned' => $pruned,
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel(NotificationConfig::logChannel())->error('push.bulk_job_failed', $this->context + [
            'tokens' => count($this->tokens),
            'error' => $exception->getMessage(),
        ]);
    }
}
