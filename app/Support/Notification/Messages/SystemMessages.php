<?php

namespace App\Support\Notification\Messages;

trait SystemMessages
{
    public static function chatMessage(string $title, mixed $message, array $extra = []): array
    {
        return self::make(
            $title,
            $message->message ?? translate('Attachment'),
            array_merge(['message' => json_encode($message), 'type' => 'message'], $extra),
        );
    }
    public static function pushNotificationRecord(string $title, string $description, mixed $image): array
    {
        return self::make($title, $description, ['image' => $image, 'type' => 'push_notification']);
    }
    public static function demoReset(): array
    {
        return [
            'title' => 'demo_reset',
            'description' => 'demo_reset',
            'image' => '',
            'order_id' => '',
            'type' => 'demo_reset',
        ];
    }
    public static function maintenanceOver(): array
    {
        return [
            'title' => translate('We are back'),
            'description' => translate('Maintenance mode is removed'),
            'image' => '',
            'order_id' => '',
        ];
    }
    public static function maintenanceStarted(): array
    {
        return [
            'title' => translate('Maintenance mode'),
            'description' => translate('We are working on something special!'),
            'image' => '',
            'order_id' => '',
        ];
    }
    public static function accountActivated(): array
    {
        return self::make(
            translate('messages.Account activation'),
            translate('messages.Your account has been activated'),
            ['type' => 'unblock'],
        );
    }
    public static function accountSuspended(): array
    {
        return self::make(
            translate('messages.suspended'),
            translate('messages.Your account has been suspended'),
            ['type' => 'block'],
        );
    }
    public static function withdrawRequestProcessed(mixed $type): array
    {
        return self::make(
            $type == 'approved' ? translate('Withdraw approved') : translate('Withdraw rejected'),
            $type == 'approved' ? translate('Withdraw request approved by admin') : translate('Withdraw request rejected by admin'),
            ['type' => 'withdraw', 'order_status' => ''],
        );
    }
}
