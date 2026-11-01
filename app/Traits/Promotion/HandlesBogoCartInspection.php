<?php

namespace App\Traits\Promotion;

use App\Models\BogoOffer;
use App\Models\BogoOfferStore;
use App\Models\BogoOfferUsage;
use App\Models\Cart;

/**
 * Reading what bundles a cart already holds, and how much allowance is left.
 *
 * The distinction this file exists to enforce: a cart GROUP is one bundle instance, an OFFER is
 * what the caps are counted against, and they are not the same number. Every add writes its own
 * group, so one offer can sit in a cart several times over -- judged group by group, two adds of
 * two each passed as "two" and put four in the cart.
 */
trait HandlesBogoCartInspection
{
    /**
     * Every bundle in the cart, keyed by group id.
     *
     * Bundle count is derived from a buy line rather than stored, so it cannot drift from the
     * rows themselves.
     */
    protected function bogoGroupsInCart($userId, int $isGuest, ?int $storeId = null): array
    {
        $rows = Cart::where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->whereNotNull('bogo_group_id')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->get()
            ->groupBy('bogo_group_id');

        $groups = [];

        foreach ($rows as $groupId => $cartRows) {
            $first = $cartRows->first();

            $enrollment = BogoOfferStore::where('bogo_offer_id', $first->bogo_offer_id)
                ->where('store_id', $first->store_id)
                ->with(['items', 'store', 'bogoOffer'])
                ->first();

            $quantity = 1;

            if ($enrollment) {
                $reference = $enrollment->items->firstWhere('type', 'buy') ?: $enrollment->items->first();
                $cartRow = $reference ? $cartRows->firstWhere('item_id', $reference->item_id) : null;

                if ($reference && $cartRow && $reference->quantity > 0) {
                    $quantity = max(1, (int) round($cartRow->quantity / $reference->quantity));
                }
            }

            $groups[$groupId] = [
                'group_id' => $groupId,
                'bogo_offer_id' => $first->bogo_offer_id,
                'enrollment' => $enrollment,
                'quantity' => $quantity,
                'rows' => $cartRows,
            ];
        }

        return $groups;
    }

    /**
     * The same cart collapsed to one entry per offer.
     *
     * The rules that cap a bundle -- the stock behind it, the offer's total cap, the customer's
     * allowance -- all apply to the offer as a whole, never to whichever group is being looked at.
     */
    protected function bogoOffersInCart($userId, int $isGuest, ?int $storeId = null): array
    {
        $offers = [];

        foreach ($this->bogoGroupsInCart($userId, $isGuest, $storeId) as $group) {
            $key = $group['bogo_offer_id'].':'.($group['enrollment']?->store_id ?? 0);

            $offers[$key] ??= [
                'bogo_offer_id' => $group['bogo_offer_id'],
                'enrollment' => $group['enrollment'],
                'quantity' => 0,
                'group_ids' => [],
            ];

            $offers[$key]['quantity'] += $group['quantity'];
            $offers[$key]['group_ids'][] = $group['group_id'];
        }

        return array_values($offers);
    }

    /**
     * How many copies of one offer's bundle the cart already holds.
     *
     * $exceptGroupId leaves out the group being rewritten, so an update is judged on what the
     * cart would hold afterwards rather than on what it holds now plus itself.
     */
    protected function bogoBundlesInCart($userId, int $isGuest, $offerId, ?int $storeId = null, ?string $exceptGroupId = null): int
    {
        $total = 0;

        foreach ($this->bogoGroupsInCart($userId, $isGuest, $storeId) as $group) {
            if ((int) $group['bogo_offer_id'] !== (int) $offerId || $group['group_id'] === $exceptGroupId) {
                continue;
            }

            $total += $group['quantity'];
        }

        return $total;
    }

    /**
     * Bundles per offer behind a set of order lines, keyed by offer id.
     *
     * Reads both shapes an order carries: models before an edit rebuilds them, and the plain
     * arrays the rebuild produces. Two groups may share one offer, so counts are summed --
     * the caps are counted per offer, never per group.
     */
    protected function bogoBundlesPerOffer($rows, int $storeId): array
    {
        $perGroup = [];

        foreach ($rows as $row) {
            $groupId = data_get($row, 'bogo_group_id');
            $offerId = data_get($row, 'bogo_offer_id');

            if (! $groupId || ! $offerId) {
                continue;
            }

            $perGroup[$groupId] ??= ['offer_id' => (int) $offerId, 'rows' => []];
            $perGroup[$groupId]['rows'][] = $row;
        }

        $enrollments = BogoOfferStore::whereIn('bogo_offer_id', array_column($perGroup, 'offer_id'))
            ->where('store_id', $storeId)
            ->with('items')
            ->get()
            ->keyBy('bogo_offer_id');

        $counts = [];

        foreach ($perGroup as $group) {
            $enrollment = $enrollments->get($group['offer_id']);
            $quantity = 1;

            if ($enrollment) {
                $reference = $enrollment->items->firstWhere('type', 'buy') ?: $enrollment->items->first();

                if ($reference && $reference->quantity > 0) {
                    foreach ($group['rows'] as $row) {
                        if ((int) data_get($row, 'item_id') === (int) $reference->item_id) {
                            $quantity = max(1, (int) round(data_get($row, 'quantity', 1) / $reference->quantity));
                            break;
                        }
                    }
                }
            }

            $counts[$group['offer_id']] = ($counts[$group['offer_id']] ?? 0) + $quantity;
        }

        return $counts;
    }

    /**
     * How many more times this customer may use the offer, or null when uncapped.
     *
     * Guests are counted by phone and never by any client-supplied identifier: a guest has no
     * user id, and anything the client can set is something the client can change to reset its
     * own allowance.
     */
    protected function bogoRemainingUses(BogoOffer $offer, $userId, int $isGuest, ?string $phone = null): ?int
    {
        if (! $offer->usage_limit_per_customer) {
            return null;
        }

        $used = (int) BogoOfferUsage::where('bogo_offer_id', $offer->id)
            ->forCustomer($userId ? (int) $userId : null, (bool) $isGuest, $phone)
            ->sum('quantity');

        return max(0, (int) $offer->usage_limit_per_customer - $used);
    }
}
