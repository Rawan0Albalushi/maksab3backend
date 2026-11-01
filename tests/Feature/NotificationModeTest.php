<?php

namespace Tests\Feature;

use App\Jobs\Notification\SendBulkPushNotification;
use App\Jobs\Notification\SendPushNotification;
use App\Support\Notification\NotificationConfig;
use App\Support\Notification\NotificationMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Support\Notification\SendNotification;

class NotificationModeTest extends TestCase
{
    private array $captured = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->captured = [];

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), 'fcm.googleapis.com')) {
                $this->captured[] = json_decode((string) $request->body(), true);
            }

            return Http::response(['name' => 'projects/x/messages/1'], 200);
        });
    }

    protected function tearDown(): void
    {
        NotificationConfig::forget();

        parent::tearDown();
    }

    private function data(): array
    {
        return ['title' => 'T', 'description' => 'D', 'image' => '', 'type' => 'order_status'];
    }

    public function test_sync_mode_delivers_on_the_request(): void
    {
        NotificationConfig::useMode(NotificationMode::Sync);

        SendNotification::sendToDevice('a-valid-token', $this->data());

        $this->assertCount(1, $this->captured, 'Sync mode should send inline.');
    }

    public function test_cron_mode_queues_instead_of_sending(): void
    {
        NotificationConfig::useMode(NotificationMode::Cron);
        Queue::fake();

        SendNotification::sendToDevice('a-valid-token', $this->data());

        $this->assertCount(0, $this->captured, 'Cron mode must not perform the HTTP call inline.');
        Queue::assertPushed(SendPushNotification::class, function ($job) {
            return $job->connection === 'database' && $job->queue === 'notifications-high';
        });
    }

    public function test_queue_mode_queues_instead_of_sending(): void
    {
        NotificationConfig::useMode(NotificationMode::Queue);
        Queue::fake();

        SendNotification::pushToTopic($this->data(), 'admin_message', 'order_request');

        $this->assertCount(0, $this->captured);
        Queue::assertPushed(SendPushNotification::class);
    }

    public function test_the_same_payload_is_produced_in_every_mode(): void
    {
        NotificationConfig::useMode(NotificationMode::Sync);
        SendNotification::sendToDevice('a-valid-token', $this->data());
        $syncPayload = $this->captured[0];

        NotificationConfig::useMode(NotificationMode::Queue);
        Queue::fake();
        SendNotification::sendToDevice('a-valid-token', $this->data());

        Queue::assertPushed(SendPushNotification::class, function ($job) use ($syncPayload) {
            return $job->payload === $syncPayload;
        });
    }

    public function test_a_rolled_back_transaction_sends_nothing(): void
    {
        NotificationConfig::useMode(NotificationMode::Sync);

        DB::beginTransaction();
        SendNotification::sendToDevice('a-valid-token', $this->data());
        $this->assertCount(0, $this->captured, 'Nothing should be sent before commit.');
        DB::rollBack();

        $this->assertCount(0, $this->captured, 'A rolled back transaction must not notify.');
    }

    public function test_a_committed_transaction_sends_after_commit(): void
    {
        NotificationConfig::useMode(NotificationMode::Sync);

        DB::beginTransaction();
        SendNotification::sendToDevice('a-valid-token', $this->data());
        $this->assertCount(0, $this->captured, 'Nothing should be sent before commit.');
        DB::commit();

        $this->assertCount(1, $this->captured, 'The push should go out once the transaction commits.');
    }

    public function test_bulk_sends_chunk_and_skip_placeholder_tokens(): void
    {
        config()->set('notification.fcm.batch_size', 2);
        NotificationConfig::useMode(NotificationMode::Sync);

        $accepted = SendNotification::pushToMany(
            ['tok-a', 'tok-b', 'tok-c', '@', '', null, 'tok-a'],
            $this->data(),
        );

        $this->assertSame(3, $accepted, 'Placeholders and duplicates should be dropped.');
        $this->assertCount(3, $this->captured);
    }

    public function test_bulk_batches_when_deferred(): void
    {
        config()->set('notification.fcm.batch_size', 2);
        NotificationConfig::useMode(NotificationMode::Queue);
        Queue::fake();

        SendNotification::pushToMany(['tok-a', 'tok-b', 'tok-c'], $this->data());

        $this->assertCount(0, $this->captured);
        Queue::assertPushed(SendBulkPushNotification::class, 2);
    }

    public function test_an_unusable_token_never_reaches_the_queue(): void
    {
        NotificationConfig::useMode(NotificationMode::Queue);
        Queue::fake();

        SendNotification::sendToDevice('@', $this->data());
        SendNotification::sendToDevice('', $this->data());

        Queue::assertNothingPushed();
    }

    public function test_after_response_mode_defers_delivery_past_the_response(): void
    {
        NotificationConfig::useMode(NotificationMode::AfterResponse);

        SendNotification::sendToDevice('a-valid-token', $this->data());

        $this->assertCount(0, $this->captured, 'Nothing may be sent while the request is still running.');

        $this->app->terminate();

        $this->assertCount(1, $this->captured, 'Delivery must happen once the response has been sent.');
    }

    public function test_after_response_mode_needs_no_queue_infrastructure(): void
    {
        NotificationConfig::useMode(NotificationMode::AfterResponse);

        $this->assertFalse(NotificationConfig::mode()->isDeferred(), 'after_response must not require a worker or cron.');
        $this->assertTrue(NotificationConfig::mode()->runsAfterResponse());
        $this->assertSame('sync', NotificationConfig::connection());
    }
}
