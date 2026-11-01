<?php

namespace App\Navigation;

use App\CentralLogics\Helpers;
use App\Models\BogoOfferStore;
use App\Models\HappyHourStore;
use App\Models\Store;

/**
 * Unanswered AND unseen admin invitations for the signed-in store, for the dashboard's prompt.
 *
 * `checked` is cleared to 1 the moment the store opens the relevant promotion list, so an
 * invitation is announced once -- the same rule the new-order popup uses -- and again only
 * when the admin raises a fresh one.
 *
 * Deliberately counts rather than loads: the prompt says only that invitations exist and sends the
 * vendor to the list, so the layout has no use for the rows themselves. That keeps this to two
 * indexed counts on every vendor page rather than two eager loads.
 */
class VendorPromotionInvitations
{
    /**
     * @return array<int, array{title: string, body: string, url: string}>
     */
    public static function pending(): array
    {
        if (! auth('vendor')->check() && ! auth('vendor_employee')->check()) {
            return [];
        }

        $store = self::store();

        // Neither feature exists outside a promotion-capable module, so there is nothing to
        // announce and nothing to query.
        if (! $store || ! config('module.'.($store->module?->module_type ?? '').'.promotions')) {
            return [];
        }

        $invites = [];

        if (self::unanswered(BogoOfferStore::query(), $store->id)) {
            $invites[] = [
                'title' => translate('BOGO campaign request'),
                'body' => translate('The admin has invited you to join a BOGO offer review and approve the request to participate'),
                'url' => route('vendor.bogo-offer.list'),
            ];
        }

        if (self::unanswered(HappyHourStore::query(), $store->id)) {
            $invites[] = [
                'title' => translate('Happy hour campaign request'),
                'body' => translate('The admin has invited you to join a happy hour campaign review and approve the request to participate'),
                'url' => route('vendor.happy-hour.list'),
            ];
        }

        return $invites;
    }

    /**
     * Pending, admin-raised, and not yet opened -- that is the whole of it.
     *
     * requested_by is what makes it an invitation: a pending row the store raised itself is
     * waiting on the admin and is not something to prompt the store about. `checked` is cleared
     * to 1 the moment the store opens the promotion list, so an already-seen invitation does not
     * keep nagging on every page until it is answered.
     */
    private static function unanswered($query, int $storeId): bool
    {
        return $query->where('store_id', $storeId)
            ->where('status', 'pending')
            ->where('requested_by', 'admin')
            ->where('checked', 0)
            ->exists();
    }

    private static function store(): ?Store
    {
        return Helpers::get_store_data();
    }
}
