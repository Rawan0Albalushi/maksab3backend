<?php

namespace Tests\Feature;

use App\Mail\TestEmailSender;
use App\Support\Notification\NotificationConfig;
use App\Support\Notification\NotificationMode;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Jobs\Notification\SendMailNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Support\Notification\SendNotification;

class MailNotificationTest extends TestCase
{
    protected function tearDown(): void
    {
        NotificationConfig::forget();
        parent::tearDown();
    }

    public function test_every_mailable_is_queueable(): void
    {
        $paths = array_merge(
            glob(base_path('app/Mail/*.php')) ?: [],
            glob(base_path('Modules/Rental/Emails/*.php')) ?: [],
            glob(base_path('Modules/Service/Emails/*.php')) ?: [],
        );

        $offenders = [];

        foreach ($paths as $path) {
            $source = file_get_contents($path);

            if (! preg_match('/class\s+\w+\s+extends\s+Mailable/', $source)) {
                continue;
            }

            if (! str_contains($source, 'implements ShouldQueue') || ! str_contains($source, 'QueueableMailable')) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame([], $offenders, 'Every mailable must be queueable so it honours the notification mode.');
    }

    public function test_mailables_declare_the_should_queue_contract(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new TestEmailSender);
    }

    public function test_mail_notifier_queues_on_the_configured_connection_when_deferred(): void
    {
        NotificationConfig::useMode(NotificationMode::Queue);
        Queue::fake();

        SendNotification::mail('someone@example.com', new TestEmailSender);

        Queue::assertPushed(SendMailNotification::class, function ($job) {
            return $job->mailable instanceof TestEmailSender
                && $job->connection === 'database'
                && $job->queue === 'notifications';
        });
    }

    public function test_mail_notifier_uses_the_sync_connection_in_sync_mode(): void
    {
        NotificationConfig::useMode(NotificationMode::Sync);
        Queue::fake();

        SendNotification::mail('someone@example.com', new TestEmailSender);

        Queue::assertPushed(SendMailNotification::class, function ($job) {
            return $job->connection === 'sync';
        });
    }

    public function test_a_blank_recipient_sends_nothing(): void
    {
        Mail::fake();

        SendNotification::mail(null, new TestEmailSender);
        SendNotification::mail('', new TestEmailSender);

        Mail::assertNothingOutgoing();
    }

    public function test_a_direct_mail_call_still_honours_the_mode(): void
    {
        NotificationConfig::useMode(NotificationMode::Queue);
        Queue::fake();

        Mail::to('someone@example.com')->send(new TestEmailSender);

        Queue::assertPushed(\Illuminate\Mail\SendQueuedMailable::class, fn ($job) => $job->mailable->connection === 'database');
    }

    public function test_switching_mode_switches_the_mail_connection(): void
    {
        Queue::fake();

        NotificationConfig::useMode(NotificationMode::Sync);
        SendNotification::mail('a@example.com', new TestEmailSender);

        NotificationConfig::useMode(NotificationMode::Cron);
        SendNotification::mail('b@example.com', new TestEmailSender);

        $connections = [];
        Queue::assertPushed(SendMailNotification::class, function ($job) use (&$connections) {
            $connections[] = $job->connection;

            return true;
        });

        $this->assertSame(['sync', 'database'], $connections, 'The mail connection must follow the notification mode.');
    }

}
