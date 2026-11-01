<?php

namespace App\Support\Notification\Fcm;

class FcmResult
{
    public function __construct(
        public readonly bool $sent,
        public readonly ?string $messageId = null,
        public readonly ?int $status = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $error = null,
        public readonly bool $tokenIsDead = false,
        public readonly bool $skipped = false,
    ) {}

    public static function ok(?string $messageId): self
    {
        return new self(sent: true, messageId: $messageId, status: 200);
    }

    public static function skipped(string $reason): self
    {
        return new self(sent: false, error: $reason, skipped: true);
    }

    public static function failed(int $status, ?string $errorCode, ?string $error, bool $tokenIsDead = false): self
    {
        return new self(
            sent: false,
            status: $status,
            errorCode: $errorCode,
            error: $error,
            tokenIsDead: $tokenIsDead,
        );
    }
}
