<?php

namespace App\Support\Notification;

use App\Jobs\Notification\SendBulkPushNotification;
use App\Jobs\Notification\SendMailNotification;
use App\Jobs\Notification\SendPushNotification;
use App\Support\Notification\Fcm\FcmMessage;
use App\Support\Notification\Fcm\FcmTokenResolver;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use App\Services\System\BusinessSettingService;

class SendNotification
{
    public static function sendOrderNotifications($order): bool
    {
        return OrderNotifier::notify($order);
    }

    public static function sendToDevice(mixed $token, array $data, ?string $webPushLink = null): void
    {
        self::deliver(self::deviceMessage((string) $token, $data, $webPushLink), ['kind' => 'device']);
    }

    public static function pushToPanel(mixed $token, array $data, ?string $webPushLink = null): void
    {
        $message = FcmMessage::toDevice((string) $token)
            ->alert(title: $data['title'] ?? '', body: $data['description'] ?? '', image: $data['image'] ?? '')
            ->data([
                'title' => $data['title'] ?? '',
                'body' => $data['description'] ?? '',
                'image' => $data['image'] ?? '',
                'order_id' => $data['order_id'] ?? '',
                'type' => $data['type'] ?? '',
                'conversation_id' => $data['conversation_id'] ?? '',
                'module_id' => $data['module_id'] ?? '',
                'sender_type' => $data['sender_type'] ?? '',
                'order_type' => $data['order_type'] ?? '',
                'click_action' => $webPushLink ?: '',
                'sound' => self::sound(),
            ]);

        self::deliver($message, ['kind' => 'panel_device']);
    }

    public static function pushToMany(array $tokens, array $data, ?string $webPushLink = null, array $context = []): int
    {
        $tokens = FcmTokenResolver::clean($tokens);

        if ($tokens === []) {
            return 0;
        }

        $payload = self::deviceMessage('', $data, $webPushLink)->toArray();
        $chunks = FcmTokenResolver::chunks($tokens);
        $context += ['kind' => 'bulk_device'];

        self::guard(function () use ($chunks, $payload, $context) {
            if (NotificationConfig::mode()->isDeferred()) {
                Bus::batch(array_map(
                    fn (array $chunk) => new SendBulkPushNotification($chunk, $payload, $context),
                    $chunks,
                ))
                    ->name($context['batch'] ?? 'push-bulk')
                    ->allowFailures()
                    ->onConnection(NotificationConfig::connection())
                    ->onQueue(NotificationConfig::queueFor('bulk'))
                    ->dispatch();

                return;
            }

            foreach ($chunks as $chunk) {
                self::afterResponse(SendBulkPushNotification::dispatch($chunk, $payload, $context));
            }
        }, $context);

        return count($tokens);
    }

    public static function pushToTopic(array $data, string $topic, string $type, ?string $webPushLink = null): void
    {
        $clickAction = $webPushLink ?: '';

        $hasOrder = isset($data['order_id']);

        $payload = [
            'title' => $data['title'] ?? '',
            'body' => $data['description'] ?? '',
        ];

        if ($hasOrder) {
            $payload['order_id'] = $data['order_id'];
            $payload['order_type'] = $data['order_type'] ?? '';
        }

        $payload['type'] = $type;
        $payload['image'] = $data['image'] ?? '';

        // The thing this topic message is ABOUT, when it is about something. Order broadcasts
        // carry their subject in `order_id` below; everything else -- a promotion going live, say
        // -- had nowhere to put one, so a client could tell what kind of message had arrived but
        // not what to open. Added only when set, so no existing broadcast gains a key (N9).
        // `module_id` / `zone_id` are listed here as well as in the order branch below. They
        // arrived by accident before: NotificationMessages::make() always sets `order_id`, so
        // `$hasOrder` was true for a promotion too and the order branch happened to add them. That
        // is not a thing to depend on.
        foreach (['data_id', 'store_id', 'module_id', 'zone_id'] as $key) {
            if (filled($data[$key] ?? null)) {
                $payload[$key] = (string) $data[$key];
            }
        }

        if ($hasOrder) {
            $payload['module_id'] = $data['module_id'] ?? '';
            $payload['zone_id'] = $data['zone_id'] ?? '';
            $payload['title_loc_key'] = $data['order_id'];
        }

        $payload['body_loc_key'] = $type;
        $payload['click_action'] = $clickAction;
        $payload['sound'] = self::sound();

        $message = FcmMessage::toTopic($topic)
            ->alert(title: $data['title'] ?? '', body: $data['description'] ?? '', image: $data['image'] ?? '')
            ->data($payload);

        self::deliver($message, ['kind' => 'topic', 'topic' => $topic]);
    }

    public static function pushSilentToTopic(array $data, string $topic, string $type): void
    {
        $message = FcmMessage::toTopic($topic)
            ->dataOnly()
            ->data([
                'title' => $data['title'] ?? '',
                'body' => $data['description'] ?? '',
                'type' => $type,
                'image' => $data['image'] ?? '',
                'body_loc_key' => $type,
            ]);

        self::deliver($message, ['kind' => 'silent_topic', 'topic' => $topic]);
    }

    public static function mail(mixed $recipient, Mailable $mailable, array $context = []): void
    {
        if (blank($recipient)) {
            return;
        }

        self::guard(
            fn () => self::afterResponse(SendMailNotification::dispatch($recipient, $mailable, $context)),
            $context + ['mailable' => $mailable::class],
        );
    }

    public static function mailTemplateEnabled(mixed $templateKey): bool
    {
        return app(BusinessSettingService::class)->value($templateKey) == '1';
    }

    public static function canSendMail(mixed $templateKey, mixed $audience = null, mixed $key = null, mixed $storeId = null): bool
    {
        return (bool) config('mail.status')
            && self::mailTemplateEnabled($templateKey)
            && ($audience === null || NotificationGate::allows($audience, $key, 'mail_status', $storeId) === 1);
    }

    public static function canSendRentalMail(mixed $templateKey, mixed $audience = null, mixed $key = null, mixed $storeId = null): bool
    {
        return (bool) config('mail.status')
            && self::mailTemplateEnabled($templateKey)
            && ($audience === null || NotificationGate::allowsForRental($audience, $key, 'mail_status', $storeId) === 1);
    }

    public static function canSendServiceMail(mixed $templateKey, mixed $audience = null, mixed $key = null, mixed $storeId = null): bool
    {
        return (bool) config('mail.status')
            && self::mailTemplateEnabled($templateKey)
            && ($audience === null || NotificationGate::allowsForService($audience, $key, 'mail_status', $storeId) === 1);
    }

    public static function channelEnabled(mixed $audience, mixed $key, mixed $channel, mixed $storeId = null): int
    {
        return NotificationGate::allows($audience, $key, $channel, $storeId);
    }

    public static function rentalChannelEnabled(mixed $audience, mixed $key, mixed $channel, mixed $storeId = null): int
    {
        return NotificationGate::allowsForRental($audience, $key, $channel, $storeId);
    }

    public static function serviceChannelEnabled(mixed $audience, mixed $key, mixed $channel, mixed $storeId = null): int
    {
        return NotificationGate::allowsForService($audience, $key, $channel, $storeId);
    }

    public static function settingFor(mixed $audience, mixed $key): ?object
    {
        return NotificationGate::statusesFor($audience, $key);
    }

    public static function forgetSettings(mixed $storeId = null): void
    {
        $storeId === null ? NotificationGate::flush() : NotificationGate::forgetStore($storeId);
    }

    public static function saveNotificationForCustomer(mixed $userId, array $data, ?string $orderType = null): void
    {
        NotificationRecorder::saveForCustomer($userId, $data, $orderType);
    }

    public static function saveNotificationForDeliveryMan(mixed $deliveryManId, array $data, ?string $orderType = null): void
    {
        NotificationRecorder::saveForDeliveryMan($deliveryManId, $data, $orderType);
    }

    public static function saveNotificationForServiceman(mixed $servicemanId, array $data, ?string $orderType = null): void
    {
        NotificationRecorder::saveForServiceman($servicemanId, $data, $orderType);
    }

    public static function pushToCustomer(mixed $userId, mixed $token, array $data, ?string $orderType = null, ?string $webPushLink = null, bool $isGuest = false): void
    {
        self::sendToDevice($token, $data, $webPushLink);

        if ($isGuest) {
            return;
        }

        NotificationRecorder::saveForCustomer($userId, $data, $orderType);
    }

    public static function pushToVendor(mixed $vendorId, mixed $token, array $data, ?string $orderType = null, ?string $webPushLink = null): void
    {
        self::sendToDevice($token, $data, $webPushLink);
        NotificationRecorder::saveForVendor($vendorId, $data, $orderType);
    }

    public static function notifyVendor(mixed $vendorId, mixed $token, mixed $storeId, array $data, ?string $webPushLink = null, ?string $orderType = null, string $topicType = 'new_order'): void
    {
        self::pushToTopic($data, "store_panel_{$storeId}_message", $topicType, $webPushLink);
        self::sendToDevice($token, $data);
        NotificationRecorder::saveForVendor($vendorId, $data, $orderType);
    }

    public static function pushToVendorPanel(mixed $vendorId, mixed $token, array $data, ?string $orderType = null, ?string $webPushLink = null): void
    {
        self::pushToPanel($token, $data, $webPushLink);
        NotificationRecorder::saveForVendor($vendorId, $data, $orderType);
    }

    public static function pushToDeliveryMan(mixed $deliveryManId, mixed $token, array $data, ?string $orderType = null, ?string $webPushLink = null): void
    {
        self::sendToDevice($token, $data, $webPushLink);
        NotificationRecorder::saveForDeliveryMan($deliveryManId, $data, $orderType);
    }

    private static function deviceMessage(string $token, array $data, ?string $webPushLink): FcmMessage
    {
        return FcmMessage::toDevice($token)
            ->alert(title: $data['title'] ?? '', body: $data['description'] ?? '', image: $data['image'] ?? '')
            ->data([
                'title' => $data['title'] ?? '',
                'body' => $data['description'] ?? '',
                'image' => $data['image'] ?? '',
                'order_id' => $data['order_id'] ?? '',
                'trip_id' => $data['trip_id'] ?? '',
                'status' => $data['status'] ?? '',
                'type' => $data['type'] ?? '',
                'data_id' => $data['data_id'] ?? '',
                'advertisement_id' => $data['advertisement_id'] ?? '',
                'conversation_id' => $data['conversation_id'] ?? '',
                'module_id' => $data['module_id'] ?? '',
                'sender_type' => $data['sender_type'] ?? '',
                'order_type' => $data['order_type'] ?? '',
                'click_action' => $webPushLink ?: '',
                'sound' => self::sound(),
            ]);
    }

    private static function deliver(FcmMessage $message, array $context): void
    {
        if (! $message->isBroadcast() && ! FcmTokenResolver::isUsable($message->target())) {
            return;
        }

        self::guard(
            fn () => self::afterResponse(SendPushNotification::dispatch($message->toArray(), $context + ['lane' => 'push'])),
            $context,
        );
    }

    private static function afterResponse(mixed $pending): void
    {
        if (NotificationConfig::mode()->runsAfterResponse() && method_exists($pending, 'afterResponse')) {
            $pending->afterResponse();
        }
    }

    private static function guard(callable $dispatch, array $context): void
    {
        try {
            $dispatch();
        } catch (\Throwable $exception) {
            Log::channel(NotificationConfig::logChannel())->error('notification.dispatch_failed', $context + [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private static function sound(): string
    {
        return (string) config('notification.fcm.sound', 'notification.wav');
    }
}
