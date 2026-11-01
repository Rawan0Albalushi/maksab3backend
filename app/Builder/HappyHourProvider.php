<?php

namespace App\Builder;

use App\Models\Store;
use App\Services\Promotion\HappyHourCatalog;
use Modules\Builder\Contracts\HappyHourProvider as HappyHourProviderContract;
use Modules\Builder\ValueObjects\StorefrontScope;

/**
 * The storefront's read-only view of a running happy hour.
 *
 * A Builder storefront is one store, so there is no zone scan to do: whether a happy hour is
 * running is asked of that store alone, through HappyHourCatalog::runningHappyHour() -- the same
 * resolver StoreDiscountResolver goes through for every item price on the site. The banner
 * therefore cannot advertise a rate the menu is not already charging.
 *
 * The window end is derived by HappyHourCatalog::windowPayload() -- the same method the customer
 * API's running-happy-hour endpoint uses -- so the storefront countdown and the customer app
 * countdown agree to the second.
 */
class HappyHourProvider implements HappyHourProviderContract
{
    public function running(?StorefrontScope $scope): ?array
    {
        $storeId = $scope?->subTenantId;

        if (! $storeId) {
            return null;
        }

        try {
            $store = Store::query()->find($storeId);

            if (! $store) {
                return null;
            }

            $catalog = app(HappyHourCatalog::class);
            $happyHour = $catalog->runningHappyHour($store);

            // A happy hour configured at 0% changes no price, so there is nothing to
            // announce -- the host's pricing resolver treats it as no happy hour too.
            if (! $happyHour || $happyHour->discount <= 0) {
                return null;
            }

            $window = $catalog->windowPayload($happyHour);
            $remainingSeconds = (int) ($window['remaining_seconds'] ?? 0);

            // The lookup says the window is open, so a remaining time of zero means the two
            // disagree (a schedule edited mid-window, a missing dated row). Rather than render
            // a banner counting down from zero, show nothing.
            if ($remainingSeconds <= 0) {
                return null;
            }

            return [
                'id' => (int) $happyHour->id,
                'title' => (string) $happyHour->title,
                'shortDescription' => $happyHour->short_description,
                'discount' => (float) $happyHour->discount,
                'minOrderAmount' => $happyHour->min_order_amount !== null
                    ? (float) $happyHour->min_order_amount
                    : null,
                'iconUrl' => $happyHour->icon_full_url,
                'coverImageUrl' => $happyHour->cover_image_full_url,
                'endsAt' => $window['ends_at'],
                'remainingSeconds' => $remainingSeconds,
            ];
        } catch (\Throwable $e) {
            // A home page must never fail over a promotion lookup.
            \info(['storefront_happy_hour' => $e->getMessage()]);

            return null;
        }
    }
}
