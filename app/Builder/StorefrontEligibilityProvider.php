<?php

namespace App\Builder;

use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Modules\Builder\Contracts\StorefrontEligibilityProvider as StorefrontEligibilityProviderContract;

/**
 * The two host facts a storefront depends on: its zone is live, and its module is connected there.
 *
 * StorefrontScopeResolver serves a site by domain and never consults either, so a storefront left
 * visible behind a switched-off zone is reachable while nothing else about that zone is -- its
 * stores, its items and its checkout are all gone. The vendor toggle asks this first so the site
 * cannot be turned on into that state, and the admin side reads the same two rules when it takes
 * a zone or a module away.
 */
class StorefrontEligibilityProvider implements StorefrontEligibilityProviderContract
{
    public function blockedReason(?int $tenantId, ?int $subTenantId): ?string
    {
        // sub_tenant_id is the store; a tenant-level scope with no store behind it has no zone
        // or module to test, so there is nothing here to refuse.
        if (! $subTenantId) {
            return null;
        }

        $store = Store::withoutGlobalScopes()
            ->with(['zone:id,name,status', 'module:id,module_name'])
            ->find($subTenantId);

        if (! $store) {
            return null;
        }

        if (! $store->zone || ! $store->zone->status) {
            return translate('messages.Your zone is currently inactive, so your website cannot be published. Please contact the admin.');
        }

        $connected = DB::table('module_zone')
            ->where('zone_id', $store->zone_id)
            ->where('module_id', $store->module_id)
            ->exists();

        if (! $connected) {
            return translate('messages.Your module is not connected to your zone, so your website cannot be published. Please contact the admin.');
        }

        return null;
    }
}
