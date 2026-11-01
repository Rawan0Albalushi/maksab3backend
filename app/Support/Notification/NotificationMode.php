<?php

namespace App\Support\Notification;

enum NotificationMode: string
{
    case Sync = 'sync';
    case AfterResponse = 'after_response';
    case Cron = 'cron';
    case Queue = 'queue';

    public static function parse(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Sync;
    }

    public function label(): string
    {
        return match ($this) {
            self::Sync => 'Immediate',
            self::AfterResponse => 'After response (no worker needed)',
            self::Cron => 'Scheduled (cron)',
            self::Queue => 'Background worker',
        };
    }

    public function isDeferred(): bool
    {
        return $this === self::Cron || $this === self::Queue;
    }

    public function runsAfterResponse(): bool
    {
        return $this === self::AfterResponse;
    }


}
