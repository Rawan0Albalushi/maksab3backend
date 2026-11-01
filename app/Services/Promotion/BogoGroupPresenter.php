<?php

namespace App\Services\Promotion;

use App\Models\BogoOfferStore;
use App\Traits\Promotion\HandlesBogoBundles;
use App\Traits\Promotion\HandlesBogoPricing;
use Illuminate\Support\Collection;

/**
 * How a cart holding BOGO bundles is presented.
 *
 * Renamed from the source's BundleCartPresenter: a BOGO group is not the only thing this codebase
 * calls a bundle, and keeping one word for both would put bundle_id and bogo_group_id in one
 * method meaning two unrelated things.
 *
 * One implementation for the cart API and the AI chat assistant. They have to agree and could
 * not be kept in step by hand: a group reading as one line on one surface and as its separate
 * rows on the other is worse than either choice made consistently.
 */
class BogoGroupPresenter
{
    use HandlesBogoBundles;
    use HandlesBogoPricing;

    /**
     * Collapse each group's rows into a single entry so the cart reads the way a customer sees
     * it: one entry per ordinary item, one per bundle -- a bundle of three items is one line,
     * not three.
     *
     * The entry keeps the ordinary cart shape untouched. A group is represented by one of its
     * rows with price and quantity restated as the bundle's, so a client that only knows the old
     * format still totals the cart correctly, and everything offer-specific hangs off
     * bogo_details. That key is present on every entry and null for ordinary items.
     */
    public function present(Collection $carts, $userId, int $isGuest, ?float $happyHourPercentage = null): array
    {
        $entries = [];

        foreach ($carts as $cart) {
            if ($cart->bogo_group_id) {
                continue;
            }

            $cart->bogo_details = null;
            $entries[] = $cart;
        }

        // How many copies of each offer the whole cart holds. A group is judged on that total,
        // not on the group being rendered: an offer added twice is one allowance and one stock
        // to draw on, so both entries carry the verdict checkout will give. Resolved once here
        // rather than per group, which would re-read the cart for every bundle in it.
        $heldPerOffer = collect($this->bogoOffersInCart($userId, $isGuest))
            ->mapWithKeys(fn ($offer) => [
                $offer['bogo_offer_id'].':'.($offer['enrollment']?->store_id ?? 0) => $offer['quantity'],
            ]);

        $digits = config('round_up_to_digit', 2);

        foreach ($carts->filter(fn ($c) => $c->bogo_group_id)->groupBy('bogo_group_id') as $groupId => $rows) {
            $first = $rows->first();

            $enrollment = BogoOfferStore::where('bogo_offer_id', $first->bogo_offer_id)
                ->where('store_id', $first->store_id)
                ->with(['items.item.module', 'store', 'bogoOffer'])
                ->first();

            // Bundle count, derived from a line rather than stored so it cannot drift.
            $quantity = 1;

            if ($enrollment) {
                $reference = $enrollment->items->firstWhere('type', 'buy') ?: $enrollment->items->first();
                $row = $reference ? $rows->firstWhere('item_id', $reference->item_id) : null;

                if ($reference && $row && $reference->quantity > 0) {
                    $quantity = max(1, (int) round($row->quantity / $reference->quantity));
                }
            }

            $held = (int) ($heldPerOffer[$first->bogo_offer_id.':'.$first->store_id] ?? $quantity);

            // Same sentence checkout refuses with, so the cart and the Place Order error do not
            // describe one situation two ways. A missing enrolment means the store left the offer
            // or an admin removed it while this bundle sat here -- not a bad identifier, which is
            // what "Offer not found" sounded like.
            $reason = $enrollment
                ? $this->bogoUnavailableReason($enrollment, max($quantity, $held), $userId, $isGuest)
                : translate('messages.The added BOGO offer is unavailable.');

            $pricing = $enrollment ? $this->bogoBundlePricing($enrollment, $happyHourPercentage) : null;
            $bundlePrice = (float) ($pricing['bundle_price'] ?? 0);

            // A bundle is not one item, so everything item-specific is nulled. Leaving a real
            // item_id here would describe the bundle as whichever line happened to be first, and
            // would invite a client to call remove-item with that row's id, which the guard then
            // refuses. The keys stay present so the shape never changes.
            //
            // Cloned because the row also appears inside bogo_details.buy_items, and pointing the
            // entry at that object would make the payload reference itself.
            $entry = clone ($rows->firstWhere('is_free_item', 0) ?: $first);

            foreach (['id', 'item_id', 'item_type', 'reel_id', 'variation',
                'add_on_ids', 'add_on_qtys', 'is_free_item'] as $field) {
                $entry->{$field} = null;
            }

            // item is a relation, so nulling the attribute would not survive serialisation --
            // the loaded relation wins. Replace the relation itself.
            $entry->setRelation('item', null);

            // What the bundle costs, so sum(price x quantity) still totals the cart.
            $entry->price = round($bundlePrice, $digits);
            $entry->quantity = $quantity;
            $entry->bogo_details = [
                'bogo_group_id' => $groupId,
                'bogo_offer_id' => $first->bogo_offer_id,
                'offer_title' => $enrollment?->bogoOffer?->title,
                'offer_slug' => $enrollment?->bogoOffer?->slug,
                // The window the offer runs in, so the app can check a chosen delivery slot
                // against it before checkout. Placement applies the same rule and refuses a
                // scheduled order the offer would have expired before; sending the dates here
                // lets the customer be told while they are still picking a time.
                'offer_start_date' => $enrollment?->bogoOffer?->start_date?->format('Y-m-d H:i:s'),
                'offer_end_date' => $enrollment?->bogoOffer?->end_date?->format('Y-m-d H:i:s'),
                'bundle_id' => $enrollment?->id,
                'quantity' => $quantity,
                'bundle_price' => round($bundlePrice, $digits),
                'total_price' => round($bundlePrice * $quantity, $digits),
                'original_price' => $pricing['original_price'] ?? round($bundlePrice, $digits),
                'discount_percentage' => $pricing['discount_percentage'] ?? 0,
                'discount_amount' => $pricing['discount_amount'] ?? 0,
                'final_price' => $pricing['final_price'] ?? round($bundlePrice, $digits),
                'total_discount_amount' => round(($pricing['discount_amount'] ?? 0) * $quantity, $digits),
                'total_final_price' => round(($pricing['final_price'] ?? $bundlePrice) * $quantity, $digits),
                'is_happy_hour' => (bool) ($pricing['is_happy_hour'] ?? false),
                'is_available' => $reason === null,
                'unavailable_reason' => $reason,
                'buy_items' => $this->members($rows, 0),
                'free_items' => $this->members($rows, 1),
            ];

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * One side of a group -- its buy lines or its free ones -- as cart rows a client can render.
     *
     * The rows are sent as they are, because a member IS an ordinary cart row and every client
     * already knows that shape; they are not put through CartResource, which would drop the very
     * fields that make a member a member (is_free_item above all).
     *
     * The one thing the raw shape is missing is the item's picture. `image_full_url` is an
     * accessor rather than a column and is not in Item's $appends, so a model serialised directly
     * ships the bare `image` filename and nothing that resolves it -- the top-level cart entries
     * never showed the gap because they go through ItemResource, which reads the accessor by
     * name. Appended per instance here rather than added to the model's $appends, which would put
     * a disk lookup on every item payload in the app to fix a key only this one is short of.
     */
    private function members(Collection $rows, int $isFreeItem): array
    {
        return $rows->where('is_free_item', $isFreeItem)
            ->each(fn ($row) => $row->item?->append('image_full_url'))
            ->values()
            ->all();
    }
}
