<?php

namespace App\Services\Promotion;

use App\Models\HappyHour;
use App\Models\Store;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Carbon;

/**
 * Reading happy hours: which stores are in one, which window is live, and how a window is
 * described.
 *
 * One set of rules for every caller -- the customer API, the storefront banner and the AI chat
 * assistant all read from here, so "is anything on happy hour?" cannot be answered two ways.
 *
 * A happy hour is MODULE-scoped and carries no zone of its own. The $zoneIds every method takes
 * are the CUSTOMER's, and they filter stores rather than windows: a window reaches somebody only
 * through an approved enrolment, and the enrolled store carries the zone.
 *
 * Whether a window is running right now can only be decided in PHP, which is the other half of
 * why this lives in one place.
 */
class HappyHourCatalog
{
    /**
     * When today's window closes, for the countdown on the banner.
     *
     * A window never crosses midnight -- the admin form caps a start time precisely so it cannot
     * -- so today's date plus the end time is always the right instant.
     */
    public function windowPayload(HappyHour $happyHour, array $zoneIds = [], ?int $moduleId = null): array
    {
        $today = now()->toDateString();

        // A permanent weekly rule writes no dated rows and is matched from its definition.
        $row = $happyHour->is_permanent
            ? null
            : $happyHour->dates()->whereDate('applicable_date', $today)->first();

        $startsAt = Carbon::parse($today.' '.($row?->start_time ?? $happyHour->start_time));
        $endsAt = Carbon::parse($today.' '.($row?->end_time ?? $happyHour->end_time));

        return [
            'started_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
            // What the countdown ticks down from. Whole seconds -- diffInSeconds returns a float
            // and a banner counting "1199.053584" is not what anyone wants. Never negative.
            'remaining_seconds' => max(0, (int) now()->diffInSeconds($endsAt, false)),
            // Counted the same way the banner is decided, so a live banner always names at least
            // one store.
            'store_count' => $this->servableBy($happyHour->enrollments(), $zoneIds, $moduleId)->count(),
        ];
    }

    /**
     * Enrolments a customer here could actually order from: approved, and pointing at a store
     * that is switched on and reachable from where they are.
     *
     * One rule, used both to decide whether a happy hour counts as running and to count its
     * stores, so the two can never disagree. Empty $zoneIds leaves the zone open, which is what a
     * caller that has already scoped its query wants.
     *
     * This is where a happy hour's geographic reach is decided, now that the window itself has no
     * zone: it goes exactly as far as the stores that enrolled in it.
     */
    public function servableBy($query, array $zoneIds = [], ?int $moduleId = null)
    {
        return $query->approved()->whereHas('store', function ($store) use ($zoneIds, $moduleId) {
            $store->active();

            if ($zoneIds) {
                $store->whereIn('zone_id', $zoneIds);
            }

            if ($moduleId) {
                $store->where('module_id', $moduleId);
            }
        });
    }

    /**
     * Stores here taking part in a happy hour.
     *
     * The enrolment has to be approved AND the happy hour active and of this module: a window
     * reaches a customer only through an approved enrolment, so anything less would list a store
     * advertising a discount it will not honour. Zone is applied to the STORE, which is what the
     * customer is actually near.
     */
    public function storesQuery(array $zoneIds, int $moduleId, $longitude = null, $latitude = null)
    {
        $scopeHappyHour = fn ($h) => $h->active()->where('module_id', $moduleId);

        return Store::active()
            ->whereIn('zone_id', $zoneIds)
            ->where('module_id', $moduleId)
            ->whereHas('happyHourEnrollments', function ($q) use ($scopeHappyHour) {
                $q->approved()->whereHas('happyHour', $scopeHappyHour);
            })
            ->with(['happyHourEnrollments' => function ($q) use ($scopeHappyHour) {
                $q->approved()->with(['happyHour' => $scopeHappyHour]);
            }])
            ->withOpen($longitude, $latitude);
    }

    /**
     * The same query reduced to the stores whose window is open right now.
     *
     * A window is a schedule rather than a column, so "running now" can only be decided in PHP.
     * Filtering that way would break the paginator's count, so it reduces to a set of ids first
     * and the caller pages over those.
     */
    public function restrictToLive($query, array $zoneIds, int $moduleId, $longitude = null, $latitude = null)
    {
        $liveIds = (clone $query)->get()
            ->filter(fn (Store $store) => (bool) $this->runningHappyHour($store))
            ->pluck('id');

        $scopeHappyHour = fn ($h) => $h->active()->where('module_id', $moduleId);

        return Store::active()->whereIn('id', $liveIds)
            ->with(['happyHourEnrollments' => function ($q) use ($scopeHappyHour) {
                $q->approved()->with(['happyHour' => $scopeHappyHour]);
            }])
            ->withOpen($longitude, $latitude);
    }

    /**
     * The happy hour this store is currently running, or null.
     *
     * Resolved from the store's own approved enrolments rather than from a zone sweep, because
     * this is asked per store while a listing is primed. The enrolments are expected to be eager
     * loaded by the caller -- lazy loading throws outside production, and this is exactly the
     * per-row lookup that would trip it.
     */
    public function runningHappyHour(Store $store): ?HappyHour
    {
        $enrollments = $store->relationLoaded('happyHourEnrollments')
            ? $store->happyHourEnrollments
            : $this->cachedEnrollments($store);

        foreach ($enrollments as $enrollment) {
            if ($enrollment->status !== 'approved') {
                continue;
            }

            $happyHour = $enrollment->happyHour;

            if ($happyHour && $happyHour->status && $happyHour->isRunningNow()) {
                return $happyHour;
            }
        }

        return null;
    }

    /**
     * The fallback DB fetch, cached per store -- a caller who didn't eager-load
     * (POSController::addToCart() is one: each add is its own HTTP request, so a store's
     * enrolments were being re-queried from scratch on every single item added to the same cart)
     * no longer repeats it for a store that hasn't changed.
     *
     * Caches the ROWS only, never the "is it running now" verdict -- that is still decided fresh
     * in PHP on every call against these rows, so a window opening or closing mid-cache is never
     * stale. Tagged 'store', busted immediately by HappyHour::$cacheTags / HappyHourStore::
     * $cacheTags on any write (create/update/status/enrolment change).
     */
    private function cachedEnrollments(Store $store): \Illuminate\Support\Collection
    {
        return ApiCache::remember(
            'store_happy_hour_enrollments',
            $store->id,
            // `happyHour.dates` eager loaded too: isRunningNow() (called on every approved
            // enrolment while resolving which window is live) queries $this->dates() itself, so a
            // store enrolled in more than one happy hour re-triggers that query once per
            // enrolment — the N+1 Debugbar flags as "HappyHour => HappyHourDate". Same gap as
            // POSController::index()'s own happyHourEnrollments eager-load, just reached from
            // POS's add-to-cart path instead of the initial page load.
            fn () => $store->happyHourEnrollments()->approved()->with('happyHour.dates')->get()
        );
    }

    /**
     * The one happy hour a customer here would be under, or null.
     *
     * Exactly one, never a list: a module cannot hold two overlapping happy hours, because the
     * admin side refuses a schedule that collides with an existing one in either order of
     * creation, and a switched-off record still holds its slot.
     *
     * The zone narrows the ENROLMENTS, not the window: a module-wide happy hour is only under way
     * here if a store here has joined it.
     */
    public function runningIn(array $zoneIds, int $moduleId): ?HappyHour
    {
        return HappyHour::active()
            ->where('module_id', $moduleId)
            ->whereHas('enrollments', fn ($q) => $this->servableBy($q, $zoneIds, $moduleId))
            ->get()
            ->first(fn (HappyHour $candidate) => $candidate->isRunningNow());
    }
}
