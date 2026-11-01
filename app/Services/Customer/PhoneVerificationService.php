<?php

namespace App\Services\Customer;

use App\Models\PhoneVerification;
use App\Services\BaseService;
use App\Traits\System\HostScopedVerificationTrait;

class PhoneVerificationService extends BaseService
{
    use HostScopedVerificationTrait;

    protected function verificationModel(): string
    {
        return PhoneVerification::class;
    }

    protected function identityColumn(): string
    {
        return 'phone';
    }

    public function findByPhone(mixed $phone): mixed
    {
        return $this->baseQuery()->where('phone', $phone)->where($this->hostScope())->first();
    }

    public function upsertForPhone(mixed $phone, array $values): void
    {
        $this->upsertForIdentity($phone, $values);
    }

    public function findHostScopedByToken(mixed $phone, mixed $otp): mixed
    {
        return $this->byConditions(['phone' => $phone, 'token' => $otp])->first();
    }

    public function findHostScopedByPhone(mixed $phone): mixed
    {
        return $this->byConditions(['phone' => $phone])->first();
    }
}
