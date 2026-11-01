<?php

namespace App\Services\Customer;

use App\Models\EmailVerifications;
use App\Services\BaseService;
use App\Traits\System\HostScopedVerificationTrait;

class EmailVerificationsService extends BaseService
{
    use HostScopedVerificationTrait;

    protected function verificationModel(): string
    {
        return EmailVerifications::class;
    }

    protected function identityColumn(): string
    {
        return 'email';
    }

    public function upsertForEmail(mixed $email, array $values): void
    {
        $this->upsertForIdentity($email, $values);
    }

    public function findByTokenOnly(mixed $token): mixed
    {
        return $this->byConditions(['token' => $token])->first();
    }

    public function findHostScopedByToken(mixed $email, mixed $otp): mixed
    {
        return $this->byConditions(['email' => $email, 'token' => $otp])->first();
    }
}
