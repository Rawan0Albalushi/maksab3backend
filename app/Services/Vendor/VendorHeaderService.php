<?php

namespace App\Services\Vendor;

use App\CentralLogics\Helpers;
use App\Models\StoreWallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Request;
use App\Services\System\BusinessSettingService;

/**
 * View data for the vendor header bar.
 *
 * Replaces the two raw <?php blocks that used to sit in
 * layouts/vendor/partials/_header.blade.php -- the cash-in-hand overflow
 * warnings and the subscription-expiry banner, both of which mixed business
 * settings lookups, wallet comparisons and date maths into the template.
 *
 * Kept separate from VendorChromeService: that one supplies chrome shared by
 * several vendor views (languages, unread count, wallet), this one is only the
 * header's own alert logic.
 */
class VendorHeaderService
{
    /** Warn once the balance reaches this share of the allowed limit. */
    private const CASH_WARNING_RATIO = 0.9;

    /** Full circumference of the subscription progress ring, in SVG units. */
    private const RING_CIRCUMFERENCE = 439.6;

    private const DEFAULT_WARNING_DAYS = 7;

    public function build(?StoreWallet $wallet): array
    {
        $store = Helpers::get_store_data();
        $store?->loadMissing(['translations', 'storage', 'storeConfig', 'module']);

        // Names match what the blade already used, so the markup did not have to
        // be renamed wholesale ($store_data appears ~30 times).
        return [
            'local' => session()->has('vendor_local') ? session('vendor_local') : null,
            'store_data' => $store,
        ]
            + $this->cashInHandAlerts($wallet)
            + $this->subscriptionAlerts($store);
    }

    /**
     * Two escalating warnings: approaching the allowed cash-in-hand limit, and
     * exceeding it. Both require an unsettled (negative) wallet balance.
     */
    private function cashInHandAlerts(?object $wallet): array
    {
        $enabled = (bool) app(BusinessSettingService::class)->value('cash_in_hand_overflow_store', false);
        $limit = (float) app(BusinessSettingService::class)->value('cash_in_hand_overflow_store_amount', false);
        $collected = (float) ($wallet?->collected_cash ?? 0);

        $applies = $enabled && $collected > 0 && (float) ($wallet?->balance ?? 0) < 0;

        return [
            'showCashApproachingLimit' => $applies && ($limit * self::CASH_WARNING_RATIO) <= abs($collected),
            'showCashLimitExceeded' => $applies && $limit < $collected,
            'cashInHandLimit' => $limit,
        ];
    }

    /**
     * Subscription expiry banner. `subscriptionRingOffset` is the stroke offset
     * for the countdown ring; it stays at the full circumference when the store
     * has no subscription.
     */
    private function subscriptionAlerts(?object $store): array
    {
        $warningDays = (int) (app(BusinessSettingService::class)->value('subscription_deadline_warning_days', false)
            ?: self::DEFAULT_WARNING_DAYS);

        $subscription = $store?->store_sub;
        $expiry = $subscription?->expiry_date_parsed;

        $expiringSoon = $subscription
            && (int) $subscription->is_trial === 0
            && $expiry
            && $expiry->copy()->subDays($warningDays)->isBefore(now());

        return [
            'subscription_deadline_warning_days' => $warningDays,
            'subscription_deadline_warning_message' => app(BusinessSettingService::class)->value('subscription_deadline_warning_message', false),
            'showFreeTrialBanner' => $subscription
                && (int) $subscription->status === 1
                && (int) $subscription->is_trial === 1
                && (int) $subscription->is_canceled === 0
                && session()->get('subscription_free_trial_close_btn') !== true,
            'showSubscriptionSection' => $store
                && ! in_array($store->store_business_model, ['none', 'commission'], true)
                && ! Request::is('vendor-panel/subscription/*'),
            'showSubscriptionBadge' => $expiringSoon && Request::is('vendor-panel'),
            'showSubscriptionBanner' => $expiringSoon
                && ! Request::is('vendor-panel')
                && session()->get('subscription_renew_close_btn') !== true,
            'subscriptionIsCanceled' => (int) ($subscription?->is_canceled ?? 0) === 1,
            'subscriptionRingOffset' => $this->ringOffset($subscription, $expiry),
        ];
    }

    private function ringOffset(?object $subscription, ?Carbon $expiry): float
    {
        if (! $subscription || ! $expiry) {
            return self::RING_CIRCUMFERENCE * 10 / 100;
        }

        $validity = (int) $subscription->validity;

        if ($validity <= 0) {
            return self::RING_CIRCUMFERENCE / 100;
        }

        $remaining = Carbon::now()->diffInDays($expiry->format('Y-m-d'), false);
        $elapsed = $validity - $remaining;
        $percent = $elapsed > 0 ? ($elapsed / $validity) * 100 : 1;

        return self::RING_CIRCUMFERENCE * $percent / 100;
    }
}
