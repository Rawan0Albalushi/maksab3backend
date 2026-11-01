<?php

namespace App\Jobs;

use App\CentralLogics\Helpers;
use App\Models\MonthlyOrderReminder;
use App\Models\NotificationMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use App\Support\Notification\NotificationText;

class MonthlyOrderReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 300;

    public function __construct(public MonthlyOrderReminder $reminder) {}

    public function handle(): void
    {
        try {
            $this->reminder->refresh();
            if ($this->reminder->status !== 'pending') {
                return;
            }

            if (!Helpers::get_business_settings('monthly_order_reminder')) {
                return;
            }

            $user = $this->reminder->user;
            if (!$user || !$user->cm_firebase_token || $user->cm_firebase_token === '@') {
                return;
            }

            $notification = NotificationMessage::with(['translations' => function ($query) use ($user) {
                $query->where('locale', $user->current_language_key ?: 'en');
            }])
                ->where('key', 'monthly_order_reminder')
                ->where('status', 1)
                ->first();

            if (!$notification) {
                return;
            }

            $message = $notification->translations->first()->value ?? $notification->message;
            if (trim((string) $message) === '') {
                return;
            }

            $title = translate('Time to reorder!');
            $body  = NotificationText::format(
                value: $message,
                user_name: trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? '')),
            );

            $data = NotificationMessages::monthlyOrderReminder($title, $body, $this->reminder->order_id, $this->reminder->id);

            SendNotification::pushToCustomer($user->id, $user->cm_firebase_token, $data);

            $this->reminder->update([
                'status'      => 'sent',
                'notified_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('MonthlyOrderReminderJob handle failed', [
                'reminder_id' => $this->reminder->id ?? null,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('MonthlyOrderReminderJob failed', [
            'reminder_id' => $this->reminder->id ?? null,
            'error'       => $e->getMessage(),
        ]);
    }
}
