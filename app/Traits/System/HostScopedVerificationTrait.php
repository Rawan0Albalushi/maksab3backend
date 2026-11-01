<?php

namespace App\Traits\System;

use App\Scopes\HostScope;

trait HostScopedVerificationTrait
{
    abstract protected function verificationModel(): string;

    abstract protected function identityColumn(): string;

    protected function hostScope(): array
    {
        return ['tenant_id' => 0, 'sub_tenant_id' => 0];
    }

    public function findByToken(mixed $identity, mixed $otp): mixed
    {
        return $this->baseQuery()
            ->where([$this->identityColumn() => $identity, 'token' => $otp] + $this->hostScope())
            ->first();
    }

    public function deleteByToken(mixed $identity, mixed $otp): void
    {
        $this->baseQuery()
            ->where([$this->identityColumn() => $identity, 'token' => $otp] + $this->hostScope())
            ->delete();
    }

    public function upsertForIdentity(mixed $identity, array $values): void
    {
        $this->baseQuery()->updateOrInsert([$this->identityColumn() => $identity] + $this->hostScope(), $values);
    }

    protected function byConditions(array $conditions): mixed
    {
        $model = $this->verificationModel();

        return $model::where($conditions);
    }

    protected function baseQuery(): mixed
    {
        $model = $this->verificationModel();

        return $model::withoutGlobalScope(HostScope::class);
    }
}
