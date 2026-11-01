<?php

namespace App\Jobs\Notification;

use App\Support\Notification\Fcm\DeadTokenPruner;
use App\Support\Notification\Fcm\FcmClient;
use App\Support\Notification\NotificationConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPushNotification implements ShouldQueue, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public array $backoff;

    public int $timeout;

    public function __construct(
        public readonly array $payload,
        public readonly array $context = [],
    ) {
        $this->tries = NotificationConfig::tries();
        $this->backoff = NotificationConfig::backoff();
        $this->timeout = NotificationConfig::timeout();

        $this->onConnection(NotificationConfig::connection());
        $this->onQueue(NotificationConfig::queueFor($context['lane'] ?? 'push'));
    }

    public function handle(FcmClient $client): void
    {
        $result = $this->connection === 'sync'
            ? $client->sendRawQuietly($this->payload, $this->context)
            : $client->sendRaw($this->payload, $this->context);

        if ($result->tokenIsDead) {
            DeadTokenPruner::prune(data_get($this->payload, 'message.token'));
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel(NotificationConfig::logChannel())->error('push.job_failed', $this->context + [
            'error' => $exception->getMessage(),
        ]);
    }
}
