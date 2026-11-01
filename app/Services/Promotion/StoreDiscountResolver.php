<?php

namespace App\Services\Promotion;

use App\Models\Store;

/**
 * The single answer to "what store-wide rate applies right now, and who set it".
 *
 * Extracted because 6amMart has two implementations of the same precedence table:
 * Helpers::product_discount_calculate() and Helpers::service_discount_calculate() each fold a
 * store rate against a row's own rate. They differ in ways that are legitimate -- services carry
 * no flash sale and label their own rate 'service_discount' -- which is why the shared part has to
 * live in one place.
 *
 *     flash_sale  >  happy_hour  >  store_discount  >  item_discount
 *
 * flash_sale short-circuits before this resolver is reached: it replaces the price outright rather
 * than folding. A happy hour outranks the vendor's own standing discount rather than adding to it,
 * because `discounts` is the vendor's tool and a happy hour is an admin campaign they opted into
 * -- stacking them would charge the store twice for one window.
 */
class StoreDiscountResolver
{
    public function __construct(private HappyHourCatalog $happyHours) {}

    /**
     * The store-wide rate in force, or null when there is none.
     *
     * Shape matches what Helpers::get_store_discount() has always returned, plus an additive
     * `source` so callers can label the result and the accounting can find the right bearer:
     *
     *   happy_hour      -- an admin campaign, borne 100% by the vendor, own expense type
     *   store_discount  -- the vendor's own rate, split with the admin on commission stores
     *
     * The bearer difference is not cosmetic: for a happy hour, commission is charged on the price
     * BEFORE the window took its cut, so charging it on the discounted total would quietly make
     * the admin a co-sponsor.
     */
    public function resolve(?Store $store): ?array
    {
        if (! $store) {
            return null;
        }

        if ($happyHour = $this->happyHours->runningHappyHour($store)) {
            return [
                'discount' => (float) $happyHour->discount,
                'min_purchase' => (float) ($happyHour->min_order_amount ?? 0),
                // A happy hour has no ceiling. null is what Helpers::checkAdminDiscount() reads
                // as uncapped -- a 0 would clamp the discount to nothing, and a sentinel like
                // PHP_FLOAT_MAX would survive the clamp but print as a real cap anywhere the
                // shape is rendered.
                'max_discount' => null,
                'source' => 'happy_hour',
                'happy_hour_id' => $happyHour->id,
            ];
        }

        return $this->vendorDiscount($store);
    }

    /**
     * The vendor's own standing discount, if one is live now.
     *
     * Kept here rather than in Helpers so both callers read the same window logic.
     */
    public function vendorDiscount(?Store $store): ?array
    {
        $discount = $store?->discount;

        if (! $discount) {
            return null;
        }

        $today = now()->format('Y-m-d');
        $time = now()->format('H:i');

        $withinDates = date('Y-m-d', strtotime($discount->start_date)) <= $today
            && date('Y-m-d', strtotime($discount->end_date)) >= $today;

        $withinHours = date('H:i', strtotime($discount->start_time)) <= $time
            && date('H:i', strtotime($discount->end_time)) >= $time;

        if (! $withinDates || ! $withinHours) {
            return null;
        }

        return [
            'discount' => (float) $discount->discount,
            'min_purchase' => (float) $discount->min_purchase,
            'max_discount' => (float) $discount->max_discount,
            'source' => 'store_discount',
            'happy_hour_id' => null,
        ];
    }

    /**
     * Who pays for the store-wide discount on this order.
     *
     * A happy hour is the vendor's own promotion, so the vendor bears it -- which is what they
     * agree to when they enrol. Anything else is the admin's standing store discount, which the
     * admin reimburses: the behaviour every order path had before happy hour existed.
     *
     * Stated here rather than at the four order-building sites because getting it wrong is
     * silent. It reconciles a few percent out rather than failing, and only in the direction of
     * the admin quietly co-sponsoring someone else's campaign.
     */
    public function bearerFor(?array $resolved): string
    {
        return ($resolved['source'] ?? null) === 'happy_hour' ? 'vendor' : 'admin';
    }

    /**
     * The discount_type label a resolved rate is reported under.
     *
     * Order details, reports, invoices and the storefront badge all name the promotion from this,
     * so a happy hour reporting itself as 'store_discount' would be billed to the wrong side.
     */
    public function labelFor(?array $resolved, string $fallback): string
    {
        return $resolved['source'] ?? $fallback;
    }
}
