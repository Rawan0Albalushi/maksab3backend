<?php

namespace App\Support\Notification\Fcm;

use RuntimeException;

class FcmException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }
}
