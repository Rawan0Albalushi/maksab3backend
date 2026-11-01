<?php

namespace App\Services\Builder;

use Illuminate\Support\Facades\DB;
use Modules\Builder\Entities\TenantDomainConfig;

/**
 * The live storefronts a zone still carries, and switching them off when it stops carrying them.
 *
 * A storefront is served by domain (App\Builder\StorefrontScopeResolver) and never re-checks the
 * zone behind it, so switching a zone off -- or disconnecting a module from one -- leaves any site
 * in it answering on its own domain while every other route into that store has gone. These two
 * questions are asked before the admin commits, so the warning can say how many sites it affects,
 * and answered again afterwards to take them down.
 *
 * Visibility is only ever turned OFF here. Re-activating a zone does not bring a site back: the
 * vendor switched it on once and the admin switched it off, so the vendor is the one to decide it
 * should return -- and by then StorefrontEligibilityProvider will let them.
 */
class StorefrontVisibilityService
{
    /**
     * How many stores in this zone are serving a storefront right now.
     *
     * @param  array<int>|null  $moduleIds  narrow to these modules, or null for the whole zone
     */
    public function liveStorefrontCount(mixed $zoneId, ?array $moduleIds = null): int
    {
        if (! $this->builderInstalled() || ! $zoneId || ($moduleIds !== null && $moduleIds === [])) {
            return 0;
        }

        return $this->liveStorefrontQuery($zoneId, $moduleIds)->count();
    }

    /**
     * Live storefront counts for a page of zones, keyed by zone id and zero-filled.
     *
     * One grouped query rather than one per row: the zone list asks this for every row it draws.
     *
     * @param  array<int>  $zoneIds
     * @return array<int, int>
     */
    public function liveStorefrontCountsByZone(array $zoneIds): array
    {
        $counts = array_fill_keys(array_map('intval', $zoneIds), 0);

        if (! $zoneIds || ! $this->builderInstalled()) {
            return $counts;
        }

        $rows = DB::table('tenant_domain_configs')
            ->join('stores', 'stores.id', '=', 'tenant_domain_configs.sub_tenant_id')
            ->where('tenant_domain_configs.website_visibility', 1)
            ->whereIn('stores.zone_id', $zoneIds)
            ->groupBy('stores.zone_id')
            ->selectRaw('stores.zone_id as zone_id, COUNT(*) as total')
            ->pluck('total', 'zone_id');

        foreach ($rows as $zoneId => $total) {
            $counts[(int) $zoneId] = (int) $total;
        }

        return $counts;
    }

    /**
     * The modules in this zone that still have at least one live storefront.
     *
     * Feeds the Connect Module drawer, which has to name which of the removed modules carries
     * sites before the admin confirms.
     *
     * @return array<int>
     */
    public function moduleIdsWithLiveStorefronts(mixed $zoneId): array
    {
        if (! $zoneId || ! $this->builderInstalled()) {
            return [];
        }

        return $this->liveStorefrontQuery($zoneId)
            ->distinct()
            ->pluck('stores.module_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Take every live storefront in this zone down, and answer how many were taken down.
     *
     * @param  array<int>|null  $moduleIds  narrow to these modules, or null for the whole zone
     */
    public function hideStorefronts(mixed $zoneId, ?array $moduleIds = null): int
    {
        if (! $this->builderInstalled() || ! $zoneId || ($moduleIds !== null && $moduleIds === [])) {
            return 0;
        }

        $ids = $this->liveStorefrontQuery($zoneId, $moduleIds)
            ->pluck('tenant_domain_configs.id')
            ->all();

        if (! $ids) {
            return 0;
        }

        return TenantDomainConfig::whereIn('id', $ids)->update(['website_visibility' => false]);
    }

    /**
     * Builder is an add-on: without it there is no tenant_domain_configs table and no
     * TenantDomainConfig class, so every question here has to answer "no storefronts" rather
     * than query. Zone setup is core and has to keep working on an install that never bought it.
     */
    private function builderInstalled(): bool
    {
        return (bool) \addon_published_status('Builder');
    }

    /**
     * Live storefronts in a zone: a visible domain config joined to the store it belongs to.
     *
     * sub_tenant_id is the store id -- see BuilderScopeResolver, which writes the pair. Joined
     * rather than resolved per store so a zone of any size costs one query.
     */
    private function liveStorefrontQuery(mixed $zoneId, ?array $moduleIds = null)
    {
        return DB::table('tenant_domain_configs')
            ->join('stores', 'stores.id', '=', 'tenant_domain_configs.sub_tenant_id')
            ->where('tenant_domain_configs.website_visibility', 1)
            ->where('stores.zone_id', $zoneId)
            ->when($moduleIds !== null, fn ($query) => $query->whereIn('stores.module_id', $moduleIds));
    }
}
