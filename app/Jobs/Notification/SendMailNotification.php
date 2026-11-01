<?php

namespace App\Jobs\Notification;

use App\Support\Notification\NotificationConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMailNotification implements ShouldQueue, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public array $backoff;

    public int $timeout;

    public function __construct(
        public readonly mixed $recipient,
        public readonly Mailable $mailable,
        public readonly array $context = [],
    ) {
        $this->tries = NotificationConfig::tries();
        $this->backoff = NotificationConfig::backoff();
        $this->timeout = NotificationConfig::timeout();

        $this->onConnection(NotificationConfig::connection());
        $this->onQueue(NotificationConfig::queueFor('mail'));
    }

    public function handle(): void
    {
        if ($this->connection === 'sync') {
            try {
                Mail::to($this->recipient)->sendNow($this->mailable);
            } catch (\Throwable $exception) {
                $this->report($exception);
            }

            return;
        }

        Mail::to($this->recipient)->sendNow($this->mailable);
    }

    public function failed(\Throwable $exception): void
    {
        $this->report($exception);
    }

    private function report(\Throwable $exception): void
    {
        Log::channel(NotificationConfig::logChannel())->error('mail.send_failed', $this->context + [
            'mailable' => $this->mailable::class,
            'error' => $exception->getMessage(),
        ]);
    }
}
