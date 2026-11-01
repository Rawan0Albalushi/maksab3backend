<?php

namespace App\Mail\Concerns;

use App\Support\Notification\NotificationConfig;
use Illuminate\Contracts\Queue\Factory as Queue;

trait QueueableMailable
{
    public function queue(Queue $queue)
    {
        $this->applyDeliveryMode();

        return parent::queue($queue);
    }

    public function later($delay, Queue $queue)
    {
        $this->applyDeliveryMode();

        return parent::later($delay, $queue);
    }

    private function applyDeliveryMode(): void
    {
        $this->connection = NotificationConfig::connection();
        $this->queue = NotificationConfig::queueFor('mail');
        $this->tries = NotificationConfig::tries();
        $this->backoff = NotificationConfig::backoff();
        $this->afterCommit = true;
    }
}
