<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class HostScope implements Scope
{
    private const DEFAULT_BYPASS_GUARDS = ['admin', 'vendor', 'vendor_employee'];

    public function apply(Builder $builder, Model $model): void
    {
        $bypassGuards = (array) \config('auth.host_scope_bypass_guards', self::DEFAULT_BYPASS_GUARDS);
        foreach ($bypassGuards as $guard) {
            if (\config("auth.guards.$guard") && Auth::guard($guard)->check()) {
                return;
            }
        }

        [$tenantId, $subTenantId] = [0, 0];
        $contextClass = 'Modules\\Builder\\Services\\StorefrontContext';
        if (\class_exists($contextClass) && app()->bound($contextClass)) {
            $scope = app($contextClass)->getScope();
            if ($scope !== null) {
                $tenantId    = (int) ($scope->tenantId ?? 0);
                $subTenantId = (int) ($scope->subTenantId ?? 0);
            }
        }

        $table = $model->getTable();
        $builder->where("$table.tenant_id", $tenantId)
                ->where("$table.sub_tenant_id", $subTenantId);
    }
}
