<?php

namespace App\Services\Promotion;

use App\CentralLogics\Helpers;
use App\Models\AddOn;
use App\Models\Cart;
use App\Models\Item;
use App\Models\Store;
use App\Services\BaseService;
use App\Traits\Promotion\HandlesBogoBundles;
use App\Traits\Promotion\HandlesBogoCartInspection;
use App\Traits\Promotion\HandlesBogoPricing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A BOGO bundle inside a customer's cart.
 *
 * A bundle is not one cart row. It is N rows -- the buy lines and the free lines -- sharing a
 * `bogo_group_id`, folded back into a single entry only for display. That shape is what lets the
 * existing per-line stock check and decrement cover every member without new code.
 *
 * The group is ATOMIC: it is added, re-quantified and removed whole, never edited line by line. A
 * partial bundle is not a bundle the store enrolled, and letting a client delete one member would
 * leave a customer holding half an offer at the offer's price.
 *
 * Three ids are involved and none are interchangeable:
 *   bogo_offer_id  -- the offer, shared by every store that joined it
 *   bogo_group_id  -- one bundle instance in one cart, and the key everything groups by
 *   bundle_id      -- the enrolment (bogo_offer_store.id): this store's terms for that offer
 */
class BogoCartService extends BaseService
{
    use HandlesBogoBundles;
    use HandlesBogoCartInspection;
    use HandlesBogoPricing;

    /**
     * Add a bundle. Quantity is the number of whole bundles, so every enrolled member -- bought
     * and free -- is multiplied by it, and the rows are written as one group.
     */
    public function addBundle(array $payload): array
    {
        $enrollment = $this->resolveBogoBundleById($payload['bundle_id'], $payload['zone_ids'] ?? []);

        if (! $enrollment) {
            return ['status_code' => 404, 'code' => 'bogo_offer', 'message' => translate('messages.Offer not found')];
        }

        $this->purgeStaleBogoGroups($payload['user_id'], (int) $payload['is_guest'], $enrollment);

        $quantity = max(1, (int) ($payload['quantity'] ?? 1));

        // What the cart would hold AFTERWARDS, not this add on its own. Each add writes its own
        // group, so two adds of two were each judged as "two" and the cart ended up with four --
        // more than the stock or the customer's allowance could ever cover. Placement caught it,
        // which is the worst place to hear it.
        $wouldHold = $quantity + $this->bogoBundlesInCart(
            $payload['user_id'], (int) $payload['is_guest'], $enrollment->bogo_offer_id, $enrollment->store_id
        );

        if ($reason = $this->bogoUnavailableReason($enrollment, $wouldHold, $payload['user_id'], (int) $payload['is_guest'], $payload['phone'] ?? null)) {
            return ['status_code' => 403, 'code' => 'bogo_offer', 'message' => $reason];
        }

        // No cross-store guard here, deliberately: the cart holds several stores at once, and an
        // ordinary add has no such check either. One store per ORDER is enforced at checkout.
        $groupId = (string) Str::uuid();

        Cart::insert($this->bogoCartRows(
            $enrollment, $groupId, $quantity,
            $payload['user_id'], (int) $payload['is_guest'], (int) $payload['module_id']
        ));

        return [
            'status_code' => 200,
            'bogo_group_id' => $groupId,
            'quantity' => $quantity,
            'store_id' => $enrollment->store_id,
        ];
    }

    /**
     * Change how many copies of a bundle the cart holds.
     *
     * The group is rewritten from the enrolment rather than edited line by line, so a bundle
     * whose terms changed under the customer cannot be re-quantified into an inconsistent state.
     */
    public function updateBundle(array $payload): array
    {
        $groups = $this->bogoGroupsInCart($payload['user_id'], (int) $payload['is_guest']);
        $group = $groups[$payload['bogo_group_id']] ?? null;

        if (! $group || ! $group['enrollment']) {
            return ['status_code' => 404, 'code' => 'bogo_group_id', 'message' => translate('messages.Offer not found')];
        }

        $enrollment = $group['enrollment'];
        $quantity = max(1, (int) $payload['quantity']);

        // Every other group of this offer still counts; this one is being replaced, so it does
        // not count against itself.
        $wouldHold = $quantity + $this->bogoBundlesInCart(
            $payload['user_id'], (int) $payload['is_guest'],
            $enrollment->bogo_offer_id, $enrollment->store_id, $payload['bogo_group_id']
        );

        if ($reason = $this->bogoUnavailableReason($enrollment, $wouldHold, $payload['user_id'], (int) $payload['is_guest'], $payload['phone'] ?? null)) {
            return ['status_code' => 403, 'code' => 'bogo_offer', 'message' => $reason];
        }

        DB::transaction(function () use ($payload, $enrollment, $quantity) {
            $this->groupRows($payload['user_id'], (int) $payload['is_guest'], $payload['bogo_group_id'])->delete();

            Cart::insert($this->bogoCartRows(
                $enrollment, $payload['bogo_group_id'], $quantity,
                $payload['user_id'], (int) $payload['is_guest'], (int) $payload['module_id']
            ));
        });

        return [
            'status_code' => 200,
            'bogo_group_id' => $payload['bogo_group_id'],
            'quantity' => $quantity,
            'store_id' => $enrollment->store_id,
        ];
    }

    /** Remove a bundle whole. Individual members are never removable. */
    public function removeBundle(array $payload): array
    {
        $deleted = $this->groupRows($payload['user_id'], (int) $payload['is_guest'], $payload['bogo_group_id'])->delete();

        if (! $deleted) {
            return ['status_code' => 404, 'code' => 'bogo_group_id', 'message' => translate('messages.Offer not found')];
        }

        return ['status_code' => 200];
    }

    /**
     * Whether this cart row belongs to a bundle, and so may not be touched directly.
     *
     * Asked by the ordinary update and remove-item paths before they act.
     */
    public function belongsToBundle(?object $cart): bool
    {
        return (bool) ($cart?->bogo_group_id);
    }

    /**
     * What a store-wide discount would come to on this cart, and how much more the basket needs.
     *
     * Separate from the cart list because that answers with rows; this answers with one verdict
     * about the basket as a whole, which is what the "spend X more to save Y" line needs.
     */
    public function discountEligibility(array $owner, ?Store $store): array
    {
        $empty = [
            'source' => null, 'percentage' => 0.0, 'min_purchase' => 0.0, 'max_discount' => null,
            'qualifying_amount' => 0.0, 'shortfall' => 0.0, 'is_qualified' => false, 'discount_amount' => 0.0,
        ];

        if (! $store) {
            return $empty;
        }

        $rate = Helpers::get_store_discount($store);

        if (! $rate || ($rate['discount'] ?? 0) <= 0) {
            return $empty;
        }

        $rows = Cart::where('user_id', $owner['user_id'])
            ->where('is_guest', $owner['is_guest'])
            ->where('store_id', $store->id)
            ->get();

        if ($rows->isEmpty()) {
            return $empty;
        }

        $isHappyHour = ($rate['source'] ?? null) === 'happy_hour';
        $qualifying = $this->qualifyingTotal($rows, $isHappyHour, (int) $store->id);

        $minPurchase = (float) ($rate['min_purchase'] ?? 0);
        $maxDiscount = (float) ($rate['max_discount'] ?? 0);
        $isQualified = $qualifying >= $minPurchase;

        $discountAmount = $isQualified ? $qualifying * ((float) $rate['discount'] / 100) : 0.0;

        // Zero is this codebase's "no cap" value, not a cap of nothing -- see
        // StoreDiscountResolver, where a happy hour reports 0 precisely because it has no ceiling.
        if ($maxDiscount > 0 && $discountAmount > $maxDiscount) {
            $discountAmount = $maxDiscount;
        }

        $digits = config('round_up_to_digit', 2);

        return [
            'source' => $rate['source'] ?? null,
            'percentage' => (float) $rate['discount'],
            'min_purchase' => $minPurchase,
            'max_discount' => $maxDiscount > 0 ? $maxDiscount : null,
            'qualifying_amount' => round($qualifying, $digits),
            'shortfall' => round(max(0, $minPurchase - $qualifying), $digits),
            'is_qualified' => $isQualified,
            'discount_amount' => round($discountAmount, $digits),
        ];
    }

    /**
     * The part of the basket a store-wide rate may actually be charged on.
     *
     * `carts.price` is whatever the client posted, and different clients hold a unit price there
     * while others hold the line total -- multiplying by quantity is right for one and doubles the
     * other. Order placement does not read that column at all: it re-derives a unit price from the
     * item plus its variation and multiplies itself. This does the same, so the figure the cart
     * promises is built exactly the way the bill will be.
     */
    private function qualifyingTotal(Collection $rows, bool $isHappyHour, int $storeId): float
    {
        $items = Item::withoutGlobalScopes()
            ->with('module')
            ->whereIn('id', $rows->pluck('item_id')->filter()->unique()->all())
            ->get()->keyBy('id');

        $addOnPrices = AddOn::whereIn(
            'id',
            $rows->flatMap(fn ($row) => Helpers::decodeJsonToArray($row->add_on_ids))->unique()->filter()->all()
        )->pluck('price', 'id');

        $frozen = $this->bogoFrozenUnitPrices($rows, $storeId);
        $bundleService = app(BundleOrderService::class);
        $bundleFrozen = $bundleService->frozenPrices($rows, $storeId);
        $total = 0.0;

        // Bundle lines are held back and totalled per group instead of added as they come, because
        // the window is not charged on what their members are worth. The bundle takes its own
        // percentage off first and the window cuts what is left (BundleOrderService::
        // groupReduction()), so a group only reaches this total once its own discount is off --
        // which cannot be done line by line, the percentage belongs to the group.
        $bundleGroups = [];

        foreach ($rows as $row) {
            // A BOGO or product-bundle line is already the promotion, so a standing store
            // discount is not taken off it as well -- same rule PlaceNewOrderTrait::makeOrderDetails()
            // applies via isDiscountable()/BundleOrderService::isDiscountable() when it builds
            // $discountable_price. A happy hour is different in kind and reaches both: a BOGO line
            // through bogoLineIsDiscountable(), and a bundle group through
            // BundleOrderService::distributeReduction(), which takes the happy-hour percentage off
            // the group and books it as happy_hour_discount. Bundles were excluded here either
            // way, so their amount never counted toward the happy hour's minimum purchase even
            // though checkout went on to discount them -- a cart could miss the threshold on this
            // endpoint and still be reduced when the order was placed.
            if (! $this->bogoLineIsDiscountable($row, $isHappyHour)
                || ($row->bundle_group_id && ! $isHappyHour)) {
                continue;
            }

            $item = $items[$row->item_id] ?? null;

            if (! $item) {
                continue;
            }

            $unit = (float) $item->price;
            $selected = Helpers::decodeJsonToArray($row->variation);

            // Food items keep their variant surcharges in `food_variations`, matched by
            // name/label (Helpers::get_varient) and added on top of the base price -- the plain
            // `variations` column, matched by `type` (Helpers::variation_price), only applies to
            // non-food modules, where the matched variation's price is the item's *absolute*
            // price for that combination, not a surcharge. See PlaceNewOrderTrait::makeOrderDetails()
            // for the same food-additive/non-food-absolute split this mirrors.
            if (($item->module?->module_type ?? null) === 'food') {
                $foodVariations = Helpers::decodeJsonToArray($item->food_variations) ?: [];

                if ($foodVariations && $selected) {
                    $unit += (float) Helpers::get_varient($foodVariations, $selected)['price'];
                }
            } else {
                $variations = Helpers::decodeJsonToArray($item->variations) ?: [];

                if ($variations && $selected) {
                    $unit = (float) Helpers::variation_price($item, json_encode($selected))['price'];
                }
            }

            if ($row->bogo_group_id) {
                [$unit] = $this->bogoLinePrice($frozen, $row, $unit);
            } elseif ($row->bundle_group_id) {
                // The bundle's own frozen unit price, which is the base distributeReduction()
                // takes the happy-hour percentage off -- PlaceNewOrderTrait prices the line the
                // same way. The catalog price would count the member at its full worth and
                // overstate what the bundle actually contributes.
                $unit = $bundleService->linePrice($bundleFrozen, $row, $unit);
            }

            // Addon cost is a flat per-line amount, not per unit of the item -- see
            // Helpers::calculate_addon_price() and PlaceNewOrderTrait::makeOrderDetails(), neither
            // of which scales it by the line's quantity. It must stay outside the `$unit * quantity`
            // multiplication below or it gets multiplied by quantity a second time.
            //
            // Zeroed for a BOGO line (buy or free) because placement zeroes it too --
            // PlaceNewOrderTrait::makeOrderDetails() sets total_add_on_price to 0 for every bogo
            // line before it ever reaches discountable_price. Counting it here promised a cut on
            // an add-on cost the order never actually charges, the same reason the bundle branch
            // below already leaves it out.
            $addonFlat = 0.0;
            if (! $row->bogo_group_id) {
                foreach (Helpers::decodeJsonToArray($row->add_on_ids) as $index => $addOnId) {
                    $qtys = Helpers::decodeJsonToArray($row->add_on_qtys);
                    $addonFlat += (float) ($addOnPrices[$addOnId] ?? 0) * (int) ($qtys[$index] ?? 1);
                }
            }

            if ($row->bundle_group_id) {
                // Item price only. distributeReduction() is handed the order's detail rows, whose
                // `price` is the item's alone -- add-ons travel beside them in total_add_on_price
                // and no bundle or window rate is ever taken off them. Counting them here would
                // promise a cut on money the bill never discounts.
                $bundleGroups[$row->bundle_group_id]['bundle_id'] = $row->bundle_id;
                $bundleGroups[$row->bundle_group_id]['amount'] =
                    ($bundleGroups[$row->bundle_group_id]['amount'] ?? 0.0) + ($unit * (int) $row->quantity);

                continue;
            }

            $total += ($unit * (int) $row->quantity) + $addonFlat;
        }

        return $total + $this->bundleGroupsQualifying($bundleGroups);
    }

    /**
     * What the held-back bundle groups contribute, each with its own discount already taken off.
     *
     * The figure a happy hour is charged on for a bundle of 1790 discounted 10% is 1611, not 1790
     * -- exactly what the order will be built from. Counting the members at their full worth made
     * this endpoint quote a cut bigger than the one the customer was then billed, and let a cart
     * clear a minimum purchase it does not actually meet.
     */
    private function bundleGroupsQualifying(array $groups): float
    {
        if (! $groups) {
            return 0.0;
        }

        $bundles = app(BundleService::class)->findManyForPricing(array_column($groups, 'bundle_id'));
        $total = 0.0;

        foreach ($groups as $group) {
            $percentage = (float) ($bundles->get($group['bundle_id'])?->discount_percentage ?? 0);
            $percentage = min(100.0, max(0.0, $percentage));

            $total += (float) $group['amount'] * (1 - $percentage / 100);
        }

        return $total;
    }

    private function groupRows($userId, int $isGuest, string $groupId)
    {
        return Cart::where('user_id', $userId)->where('is_guest', $isGuest)->where('bogo_group_id', $groupId);
    }
}
