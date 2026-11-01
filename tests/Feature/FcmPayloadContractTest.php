<?php

namespace Tests\Feature;

use App\Support\Notification\Fcm\FcmClient;
use App\Support\Notification\Fcm\FcmException;
use App\Support\Notification\Fcm\FcmMessage;
use App\Support\Notification\NotificationConfig;
use App\Support\Notification\NotificationMode;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Support\Notification\SendNotification;

class FcmPayloadContractTest extends TestCase
{

    private array $captured = [];

    private $fcmResponder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->captured = [];
        NotificationConfig::useMode(NotificationMode::Sync);
        $this->fcmResponder = fn () => Http::response(['name' => 'projects/x/messages/1'], 200);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'test-token', 'expires_in' => 3600], 200);
            }

            if (str_contains($request->url(), 'fcm.googleapis.com')) {
                $this->captured[] = json_decode((string) $request->body(), true);

                return ($this->fcmResponder)($request);
            }

            return Http::response([], 200);
        });
    }

    private function richData(): array
    {
        return [
            'title' => 'T', 'description' => 'D', 'image' => 'IMG', 'type' => 'order_status',
            'order_id' => 11, 'trip_id' => 22, 'status' => 'ST', 'data_id' => 33,
            'advertisement_id' => 44, 'conversation_id' => 55, 'module_id' => 66,
            'sender_type' => 'customer', 'order_type' => 'delivery', 'zone_id' => 77,
        ];
    }

    private function bareData(): array
    {
        return ['title' => 'T', 'description' => 'D', 'image' => 'IMG', 'type' => 'x'];
    }

    private function sole(): array
    {
        $this->assertCount(1, $this->captured, 'Expected exactly one FCM request.');

        return $this->captured[0];
    }

    public function test_device_payload_keys_and_order_are_stable(): void
    {
        SendNotification::sendToDevice('a-valid-token', $this->richData(), 'https://link');

        $message = $this->sole()['message'];

        $this->assertSame('a-valid-token', $message['token']);
        $this->assertSame([
            'title', 'body', 'image', 'order_id', 'trip_id', 'status', 'type', 'data_id',
            'advertisement_id', 'conversation_id', 'module_id', 'sender_type', 'order_type',
            'click_action', 'sound',
        ], array_keys($message['data']));

        $this->assertSame('11', $message['data']['order_id']);
        $this->assertSame('https://link', $message['data']['click_action']);
        $this->assertSame('notification.wav', $message['data']['sound']);
        $this->assertSame(['title' => 'T', 'body' => 'D', 'image' => 'IMG'], $message['notification']);
        $this->assertSame('6ammart', $message['android']['notification']['channelId']);
        $this->assertSame('notification.wav', $message['apns']['payload']['aps']['sound']);
    }

    public function test_topic_payload_with_order_id_keeps_loc_keys(): void
    {
        SendNotification::pushToTopic($this->richData(), 'admin_message', 'order_request', 'https://link');

        $message = $this->sole()['message'];

        $this->assertSame('admin_message', $message['topic']);
        // `data_id` sits between `image` and `module_id`: pushToTopic() adds it whenever the
        // message is ABOUT something, so a client can open the subject rather than just a list.
        // It is conditional on the key being set, which is why the assertion below -- built from
        // richData(), where it IS set -- expects it while a payload without one would not carry
        // it at all.
        $this->assertSame([
            'title', 'body', 'order_id', 'order_type', 'type', 'image', 'data_id', 'module_id',
            'zone_id', 'title_loc_key', 'body_loc_key', 'click_action', 'sound',
        ], array_keys($message['data']));

        $this->assertSame('11', $message['data']['title_loc_key']);
        $this->assertSame('order_request', $message['data']['body_loc_key']);
    }

    public function test_topic_payload_without_order_id_uses_short_shape(): void
    {
        SendNotification::pushToTopic($this->bareData(), 'zone_1_customer', 'general');

        $message = $this->sole()['message'];

        $this->assertSame([
            'title', 'body', 'type', 'image', 'body_loc_key', 'click_action', 'sound',
        ], array_keys($message['data']));

        $this->assertSame('', $message['data']['click_action']);
    }

    public function test_demo_reset_and_maintenance_are_data_only(): void
    {
        SendNotification::pushSilentToTopic($this->bareData(), 'topic', 'maintenance');
        $message = $this->sole()['message'];

        $this->assertSame(['title', 'body', 'type', 'image', 'body_loc_key'], array_keys($message['data']));
        $this->assertArrayNotHasKey('notification', $message);
        $this->assertArrayNotHasKey('android', $message);
        $this->assertArrayNotHasKey('apns', $message);

        $this->captured = [];
        SendNotification::pushSilentToTopic($this->bareData(), 'topic', 'maintenance');
        $maintenance = $this->sole()['message'];

        $this->assertSame($message['data'], $maintenance['data']);
    }

    public function test_panel_push_payload_keeps_its_own_shape(): void
    {
        SendNotification::pushToPanel('a-valid-token', $this->richData(), 'https://link');

        $message = $this->sole()['message'];

        $this->assertSame([
            'title', 'body', 'image', 'order_id', 'type', 'conversation_id',
            'module_id', 'sender_type', 'order_type', 'click_action', 'sound',
        ], array_keys($message['data']));
    }

    public function test_panel_push_payload_tolerates_missing_order_id(): void
    {
        SendNotification::pushToPanel('a-valid-token', ['title' => 'T', 'description' => 'D', 'image' => '']);

        $this->assertSame('', $this->sole()['message']['data']['order_id']);
    }

    public function test_placeholder_tokens_are_skipped_without_an_http_call(): void
    {
        foreach (['@', '', null, '  '] as $token) {
            $this->captured = [];
            SendNotification::sendToDevice($token, $this->bareData());
            $this->assertCount(0, $this->captured, 'Placeholder token should not reach FCM.');
        }
    }

    public function test_retryable_failures_do_not_escape_to_the_caller(): void
    {
        $this->fcmResponder = fn () => Http::response(['error' => ['status' => 'UNAVAILABLE', 'message' => 'down']], 503);

        SendNotification::sendToDevice('a-valid-token', $this->bareData());
        SendNotification::pushToTopic($this->bareData(), 'topic', 'general');
        SendNotification::pushToPanel('a-valid-token', $this->bareData());

        $this->assertCount(3, $this->captured, 'All three sends should have reached the fake transport.');
    }

    public function test_client_reports_dead_tokens_without_asking_for_a_retry(): void
    {
        $this->fcmResponder = fn () => Http::response([
            'error' => ['status' => 'UNREGISTERED', 'message' => 'token no longer valid'],
        ], 404);

        $result = app(FcmClient::class)->sendRaw(
            FcmMessage::toDevice('a-valid-token')->alert('T', 'D')->data(['type' => 'x'])->toArray(),
        );

        $this->assertFalse($result->sent);
        $this->assertTrue($result->tokenIsDead);
        $this->assertSame('UNREGISTERED', $result->errorCode);
    }

    public function test_client_throws_on_retryable_status_so_the_queue_can_retry(): void
    {
        $this->fcmResponder = fn () => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503);

        $this->expectException(FcmException::class);

        app(FcmClient::class)->sendRaw(
            FcmMessage::toDevice('a-valid-token')->alert('T', 'D')->data(['type' => 'x'])->toArray(),
        );
    }

    public function test_client_reports_the_message_id_on_success(): void
    {
        $result = app(FcmClient::class)->sendRaw(
            FcmMessage::toDevice('a-valid-token')->alert('T', 'D')->data(['type' => 'x'])->toArray(),
        );

        $this->assertTrue($result->sent);
        $this->assertSame('projects/x/messages/1', $result->messageId);
    }

    protected function tearDown(): void
    {
        NotificationConfig::forget();
        parent::tearDown();
    }
}
