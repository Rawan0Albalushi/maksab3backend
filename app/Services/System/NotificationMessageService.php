<?php

namespace App\Services\System;

use App\Models\NotificationMessage;
use App\Services\BaseService;

class NotificationMessageService extends BaseService
{
    private const ORDER_STATUS_KEYS = [
        'pending' => 'order_pending_message',
        'confirmed' => 'order_confirmation_msg',
        'processing' => 'order_processing_message',
        'picked_up' => 'out_for_delivery_message',
        'handover' => 'order_handover_message',
        'delivered' => 'order_delivered_message',
        'delivery_boy_delivered' => 'delivery_boy_delivered_message',
        'accepted' => 'delivery_boy_assign_message',
        'canceled' => 'order_cancled_message',
        'refunded' => 'order_refunded_message',
        'refund_request_canceled' => 'refund_request_canceled',
        'offline_verified' => 'offline_order_accept_message',
        'offline_denied' => 'offline_order_deny_message',
    ];

    public function forOrderStatus(mixed $status, mixed $moduleType, string $locale = 'en'): mixed
    {
        $key = self::ORDER_STATUS_KEYS[$status] ?? null;

        if (! $key) {
            return 0;
        }

        $data = NotificationMessage::with([
            'translations' => fn ($query) => $query->where('locale', $locale),
        ])->where('module_type', $moduleType)->where('key', $key)->first();

        if (! $data) {
            return false;
        }

        if ($data->status == 0) {
            return 0;
        }

        return count($data->translations) > 0 ? $data->translations[0]->value : $data->message;
    }

    public function isEnabled(string $key): bool
    {
        return NotificationMessage::where('key', $key)->where('status', 1)->exists();
    }

    public function findEnabledWithTranslations(string $key, string $locale): ?NotificationMessage
    {
        return NotificationMessage::with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->where('key', $key)
            ->where('status', 1)
            ->first();
    }
}
