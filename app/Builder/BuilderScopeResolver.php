<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Modules\Builder\Contracts\BuilderScopeResolver as BuilderScopeResolverContract;
use Modules\Builder\ValueObjects\StorefrontScope;

class BuilderScopeResolver implements BuilderScopeResolverContract
{
    public function resolveFromAuth(): ?StorefrontScope
    {
        if (!Auth::guard('vendor')->check() && !Auth::guard('vendor_employee')->check()) {
            return null;
        }

        // get_store_data() resolves through the auth guard's own cached user model
        // (loadMissing() on it, not a fresh query), so this is the same store instance
        // every other vendor-panel call in the request already touched -- ShareBuilderProps
        // resolves a scope once per request and BuilderController::index() resolves its own
        // right after; without sharing this, each resolution used to run its own independent
        // Store::find() (bypassing the guard's cache entirely) and paid for the 'translate'
        // global scope's translations join on top, multiplying into several duplicate queries
        // per page load.
        $vendorId = Helpers::get_vendor_id() ?: null;
        $store    = Helpers::get_store_data();
        $storeId  = $store?->id ?: null;

        return new StorefrontScope(
            tenantId: $vendorId,
            subTenantId: $storeId,
            moduleId: $store?->module_id,
            regionId: $store?->zone_id,
            logoUrl: $this->safeLogoUrl($store),
            displayName: $store?->slug ?? null,
        );
    }

    private function safeLogoUrl(?Store $store): ?string
    {
        if (!$store) {
            return null;
        }

        try {
            return $store->logo_full_url;
        } catch (\Throwable) {
            return null;
        }
    }
}
