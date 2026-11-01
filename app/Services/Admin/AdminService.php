<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Services\BaseService;

class AdminService extends BaseService
{
    private const SUPER_ADMIN_ROLE_ID = 1;

    public function findSuperAdmin(): ?Admin
    {
        return Admin::where('role_id', self::SUPER_ADMIN_ROLE_ID)->first();
    }

    public function findSuperAdminEmail(): mixed
    {
        return $this->findSuperAdmin()?->getRawOriginal('email');
    }

    public function find(mixed $id): ?Admin
    {
        return Admin::find($id);
    }
}
