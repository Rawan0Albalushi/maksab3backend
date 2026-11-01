<?php

namespace App\Support\Notification;

use App\Traits\Notification\SmsGatewayTrait;

class Sms
{
    use SmsGatewayTrait;

    public static function deliver(mixed $phone, mixed $token): string
    {
        return self::gatewayPublished()
            ? \Modules\Gateways\Traits\SmsGateway::send($phone, $token)
            : self::send($phone, $token);
    }

    public static function delivered(mixed $phone, mixed $token): bool
    {
        return self::deliver($phone, $token) === 'success';
    }

    public static function gatewayPublished(): bool
    {
        return (int) (config('get_payment_publish_status')[0]['is_published'] ?? 0) === 1;
    }
}
