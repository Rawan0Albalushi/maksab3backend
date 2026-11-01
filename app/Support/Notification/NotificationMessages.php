<?php

namespace App\Support\Notification;


use App\Support\Notification\Messages\CustomerMessages;
use App\Support\Notification\Messages\StoreMessages;
use App\Support\Notification\Messages\DeliveryManMessages;
use App\Support\Notification\Messages\OrderMessages;
use App\Support\Notification\Messages\PromotionMessages;
use App\Support\Notification\Messages\SystemMessages;

class NotificationMessages
{
    use CustomerMessages;
    use StoreMessages;
    use DeliveryManMessages;
    use OrderMessages;
    use PromotionMessages;
    use SystemMessages;

    public static function make(string $title, mixed $description, array $extra = []): array
    {
        return array_merge([
            'title' => $title,
            'description' => $description,
            'order_id' => '',
            'image' => '',
        ], $extra);
    }
}
