<?php

namespace App\Services\Promotion;

use App\CentralLogics\Helpers;
use App\Models\BogoOffer;
use App\Models\BogoOfferStore;
use App\Models\BogoOfferUsage;
use App\Services\BaseService;
use App\Traits\Promotion\HandlesBogoBundles;
use App\Traits\Promotion\HandlesBogoCartInspection;
use App\Traits\Promotion\HandlesBogoPricing;
use Illuminate\Support\Facades\DB;

class BogoOrderService extends BaseService
{
    use HandlesBogoBundles;
    use HandlesBogoCartInspection;
    use HandlesBogoPricing;

    public function blockingReason(array $owner, int $storeId, ?string $phone = null, $scheduleAt = null, ?string $orderType = null): ?string
    {
        foreach ($this->bogoOffersInCart($owner['user_id'], (int) $owner['is_guest'], $storeId) as $group) {
            // The enrolment is gone -- the store left the offer, or an admin removed it -- while
            // this bundle sat in the cart. "Offer not found" reads like a bad id; the customer
            // needs to know the thing they added is the thing that is now unavailable.
            if (! $group['enrollment']) {
                return translate('messages.The added BOGO offer is unavailable.');
            }

            $reason = $this->bogoUnavailableReason(
                $group['enrollment'],
                $group['quantity'],
                $owner['user_id'],
                (int) $owner['is_guest'],
                $phone,
                $scheduleAt,
                $orderType
            );

            if ($reason) {
                return $reason;
            }
        }

        // Checked per GROUP, separately from the offer-aggregated checks above: those are asked
        // once per offer with quantities summed across every group so a cap (usage_limit_total,
        // bogoRemainingUses) cannot be split around by holding several groups of it -- checking
        // "has this group's own selection changed" against that same summed quantity would answer
        // a different group's question. An offer can pass every status/date/cap check while one
        // particular group's frozen items no longer match what the store now enrols -- an admin or
        // vendor reworked the enrolment's items after this copy was added -- exactly what
        // HandlesBundleCart::bundleMembersChanged() catches for a bundle.
        foreach ($this->bogoGroupsInCart($owner['user_id'], (int) $owner['is_guest'], $storeId) as $group) {
            if ($group['enrollment'] && $this->bogoCombinationChanged($group['rows'], $group['enrollment'])) {
                return translate('messages.The added BOGO offer has changed. Please add it again.');
            }
        }

        return null;
    }

    public function frozenPrices($carts, ?int $storeId = null): array
    {
        return $this->bogoFrozenUnitPrices($carts, $storeId);
    }

    public function linePrice(array $frozenPrices, $cart, float $fallback): array
    {
        return $this->bogoLinePrice($frozenPrices, $cart, $fallback);
    }

    public function isDiscountable($detail, bool $isHappyHour): bool
    {
        return $this->bogoLineIsDiscountable($detail, $isHappyHour);
    }

    public function clearUnappliedRates(array $itemDetails): array
    {
        foreach (['store_discount', 'happy_hour_discount', 'vendor_store_discount', 'discount'] as $key) {
            if (array_key_exists($key, $itemDetails)) {
                $itemDetails[$key] = 0;
            }
        }

        return $itemDetails;
    }

    public function detailColumns($cart, ?float $unitWorth): array
    {
        $groupId = data_get($cart, 'bogo_group_id');

        return [
            'bogo_offer_id' => $groupId ? data_get($cart, 'bogo_offer_id') : null,
            'bogo_group_id' => $groupId,
            'is_free_item' => (int) (data_get($cart, 'is_free_item') ?? 0),

            'bogo_free_value' => data_get($cart, 'is_free_item') ? $unitWorth : null,
        ];
    }

    public function recordUsage($order, int $storeId, ?string $phone = null): void
    {
        foreach ($this->bogoGroupsInCart($order->user_id, (int) $order->is_guest, $storeId) as $group) {
            BogoOfferUsage::create([
                'bogo_offer_id' => $group['bogo_offer_id'],
                'order_id' => $order->id,
                'user_id' => $order->is_guest ? null : $order->user_id,
                'is_guest' => $order->is_guest ? 1 : 0,
                'phone' => $order->is_guest ? $phone : null,
                'quantity' => $group['quantity'],
            ]);

            BogoOffer::where('id', $group['bogo_offer_id'])->increment('total_uses', $group['quantity']);
        }
    }

    public function syncUsage($order, array $orderDetails, ?string $phone = null): void
    {
        $wanted = $this->bundlesPerOffer($orderDetails, (int) $order->store_id);

        $existing = BogoOfferUsage::where('order_id', $order->id)
            ->get()
            ->groupBy('bogo_offer_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity'))
            ->all();

        foreach (array_unique(array_merge(array_keys($wanted), array_keys($existing))) as $offerId) {
            $delta = ($wanted[$offerId] ?? 0) - ($existing[$offerId] ?? 0);

            if ($delta === 0) {
                continue;
            }

            BogoOffer::where('id', $offerId)->update([
                'total_uses' => DB::raw('GREATEST(COALESCE(total_uses, 0) + ('.(int) $delta.'), 0)'),
            ]);
        }

        BogoOfferUsage::where('order_id', $order->id)->delete();

        foreach ($wanted as $offerId => $quantity) {
            BogoOfferUsage::create([
                'bogo_offer_id' => $offerId,
                'order_id' => $order->id,
                'user_id' => $order->is_guest ? null : $order->user_id,
                'is_guest' => $order->is_guest ? 1 : 0,
                'phone' => $order->is_guest ? $phone : null,
                'quantity' => $quantity,
            ]);
        }
    }

    public function bundlesFor($rows, int $storeId): array
    {
        return $this->bundlesPerOffer(is_array($rows) ? $rows : collect($rows)->all(), $storeId);
    }

    public function stampEditorBundles($carts, int $storeId)
    {
        $counts = [];

        foreach ($carts as $row) {
            if ($groupId = data_get($row, 'bogo_group_id')) {
                $counts[$groupId] ??= $this->bundlesForGroup($carts, $groupId, $storeId);
            }
        }

        foreach ($carts as $key => $row) {
            $groupId = data_get($row, 'bogo_group_id');

            if (! $groupId) {
                continue;
            }

            $bundles = max(1, (int) ($counts[$groupId] ?? 1));

            $carts[$key] = $this->writeRow($row, [
                'bogo_bundle_quantity' => $bundles,
                'bogo_unit_quantity' => max(1, (int) round(((int) data_get($row, 'quantity', 1)) / $bundles)),
            ]);
        }

        return $carts;
    }

    public function scaleEditorBundle($carts, string $groupId, int $bundles)
    {
        $bundles = max(1, $bundles);

        foreach ($carts as $key => $row) {
            if (data_get($row, 'bogo_group_id') !== $groupId) {
                continue;
            }

            $unit = max(1, (int) (data_get($row, 'bogo_unit_quantity') ?: 1));

            $carts[$key] = $this->writeRow($row, [
                'quantity' => $unit * $bundles,
                'bogo_bundle_quantity' => $bundles,
            ]);
        }

        return $carts;
    }

    public function orderGroups($details, ?int $storeId = null): array
    {
        $rows = collect($details);
        $storeId = $storeId ?: (int) ($rows->first()?->order?->store_id ?: 0) ?: null;

        $plain = $rows->filter(fn ($d) => empty($d->bogo_group_id))->values();
        $bundles = [];

        $grouped = $rows->filter(fn ($d) => ! empty($d->bogo_group_id))->groupBy('bogo_group_id');

        $titles = BogoOffer::whereIn('id', $rows->pluck('bogo_offer_id')->filter()->unique()->all())
            ->pluck('title', 'id');

        foreach ($grouped as $groupId => $lines) {
            $quantity = $storeId ? $this->bundlesForGroup($lines, (string) $groupId, $storeId) : 1;
            $charged = (float) $lines->sum(fn ($d) => (float) $d->price * (int) $d->quantity);

            $bundles[] = [
                'group_id' => (string) $groupId,
                'offer_id' => $lines->first()->bogo_offer_id,
                'offer_title' => $titles[$lines->first()->bogo_offer_id] ?? null,
                'quantity' => max(1, $quantity),
                'unit_price' => $quantity > 0 ? $charged / $quantity : $charged,
                'total' => $charged,
                'addon_total' => (float) $lines->sum('total_add_on_price'),
                'buy' => $lines->where('is_free_item', 0)->values(),
                'free' => $lines->where('is_free_item', 1)->values(),
            ];
        }

        return ['plain' => $plain, 'bundles' => $bundles];
    }

    public function editorEntries($cartItems, ?int $storeId = null): array
    {
        $rows = collect($cartItems);
        $entries = [];
        $seen = [];

        $titles = BogoOffer::whereIn('id', $rows->pluck('bogo_offer_id')->filter()->unique()->all())
            ->pluck('title', 'id');

        foreach ($rows as $key => $row) {
            if (! data_get($row, 'status')) {
                continue;
            }

            $groupId = data_get($row, 'bogo_group_id');

            if (! $groupId) {
                $entries[] = ['is_bogo' => false, 'key' => $key, 'detail' => $row];

                continue;
            }

            if (isset($seen[$groupId])) {
                continue;
            }

            $seen[$groupId] = true;

            $lines = $rows->filter(
                fn ($r) => data_get($r, 'bogo_group_id') === $groupId && data_get($r, 'status')
            )->values();

            $bundles = (int) (data_get($row, 'bogo_bundle_quantity') ?: 0);

            if ($bundles < 1) {
                $bundles = $storeId ? $this->bundlesForGroup($rows, $groupId, $storeId) : 1;
            }

            $entries[] = [
                'is_bogo' => true,
                'key' => $key,
                'detail' => $row,
                'group_id' => $groupId,
                'lines' => $lines,
                'bundles' => max(1, $bundles),
                'title' => $titles[data_get($row, 'bogo_offer_id')] ?? null,

                'total' => (float) $lines->sum(
                    fn ($r) => ((float) data_get($r, 'price', 0) - (float) (data_get($r, 'discount_on_item') ?? 0))
                        * (int) data_get($r, 'quantity', 0)
                        + (float) (data_get($r, 'total_add_on_price') ?? 0)
                ),
            ];
        }

        return $entries;
    }

    private function writeRow($row, array $values)
    {
        foreach ($values as $field => $value) {
            data_set($row, $field, $value);
        }

        return $row;
    }

    public function editRefusalReason($order, array $newRows, array $bundlesBefore, int $storeId): ?string
    {
        $address = is_string($order->delivery_address)
            ? json_decode($order->delivery_address, true)
            : (array) $order->delivery_address;

        $at = $order->scheduled && $order->schedule_at ? $order->schedule_at : null;

        foreach ($this->bundlesPerOffer($newRows, $storeId) as $offerId => $bundles) {
            $added = $bundles - ($bundlesBefore[$offerId] ?? 0);

            if ($added <= 0) {
                continue;
            }

            $enrollment = BogoOfferStore::where('bogo_offer_id', $offerId)
                ->where('store_id', $storeId)
                ->with(['items', 'store', 'bogoOffer'])
                ->first();

            if (! $enrollment) {
                return translate('messages.The added BOGO offer is unavailable.');
            }

            $reason = $this->bogoUnavailableReason(
                $enrollment,
                $added,
                $order->is_guest ? null : $order->user_id,
                (int) $order->is_guest,
                $order->is_guest ? data_get($address, 'contact_person_number') : null,
                $at,
                $order->order_type
            );

            if ($reason) {
                return $reason;
            }
        }

        return null;
    }

    private function bundlesForGroup($carts, string $groupId, int $storeId): int
    {
        $rows = collect($carts)->filter(fn ($row) => data_get($row, 'bogo_group_id') === $groupId)->all();

        return (int) (collect($this->bundlesPerOffer($rows, $storeId))->first() ?? 1);
    }

    protected function bundlesPerOffer(array $rows, int $storeId): array
    {
        $perOffer = [];

        $groups = collect($rows)
            ->filter(fn ($row) => data_get($row, 'bogo_group_id'))
            ->groupBy(fn ($row) => data_get($row, 'bogo_group_id'));

        foreach ($groups as $group) {
            $offerId = (int) data_get($group->first(), 'bogo_offer_id');

            if (! $offerId) {
                continue;
            }

            $enrollment = BogoOfferStore::where('bogo_offer_id', $offerId)
                ->where('store_id', $storeId)
                ->with('items')
                ->first();

            $quantity = 1;

            if ($enrollment) {
                $reference = $enrollment->items->firstWhere('type', 'buy') ?: $enrollment->items->first();
                $row = $reference
                    ? $group->first(fn ($r) => (int) data_get($r, 'item_id') === (int) $reference->item_id)
                    : null;

                if ($reference && $row && $reference->quantity > 0) {
                    $quantity = max(1, (int) round((int) data_get($row, 'quantity', 1) / $reference->quantity));
                }
            }

            $perOffer[$offerId] = ($perOffer[$offerId] ?? 0) + $quantity;
        }

        return $perOffer;
    }

    public function happyHourIdFor($store): ?int
    {
        $rate = Helpers::get_store_discount($store);

        return ($rate['source'] ?? null) === 'happy_hour' ? ($rate['happy_hour_id'] ?? null) : null;
    }

    public function isHappyHourRunning($store): bool
    {
        return (Helpers::get_store_discount($store)['source'] ?? null) === 'happy_hour';
    }

    public function groupOrderDetails($details, ?int $storeId = null): array
    {
        $rows = collect($details);

        if ($rows->every(fn ($detail) => empty($detail->bogo_group_id))) {
            return $rows->each(fn ($detail) => $detail->bogo_details = null)->values()->all();
        }

        $digits = config('round_up_to_digit', 2);
        $entries = [];

        foreach ($rows as $detail) {
            if ($detail->bogo_group_id) {
                continue;
            }

            $detail->bogo_details = null;
            $entries[] = $detail;
        }

        foreach ($rows->filter(fn ($d) => $d->bogo_group_id)->groupBy('bogo_group_id') as $groupId => $group) {
            $first = $group->first();
            $enrollment = $this->enrollmentFor($first->bogo_offer_id, $storeId);

            $quantity = $this->bundleQuantityFrom($group, $enrollment);
            $charged = (float) $group->sum(fn ($d) => (float) $d->price * (int) $d->quantity);
            $gaveAway = (float) $group->sum(fn ($d) => (float) ($d->bogo_free_value ?? 0) * (int) $d->quantity);

            $entry = clone ($group->firstWhere('is_free_item', 0) ?: $first);

            foreach (['id', 'item_id', 'item_campaign_id', 'item_details', 'variation', 'add_ons', 'is_free_item'] as $field) {
                $entry->{$field} = null;
            }

            $entry->price = round($quantity > 0 ? $charged / $quantity : $charged, $digits);
            $entry->quantity = $quantity;
            $entry->bogo_details = [
                'bogo_group_id' => $groupId,
                'bogo_offer_id' => $first->bogo_offer_id,
                'offer_title' => $enrollment?->bogoOffer?->title,
                'offer_slug' => $enrollment?->bogoOffer?->slug,
                'bundle_id' => $enrollment?->id,
                'quantity' => $quantity,
                'bundle_price' => round($quantity > 0 ? $charged / $quantity : $charged, $digits),
                'total_price' => round($charged, $digits),

                'free_value' => round($gaveAway, $digits),
                'buy_items' => $group->where('is_free_item', 0)->values()->all(),
                'free_items' => $group->where('is_free_item', 1)->values()->all(),
            ];

            $entries[] = $entry;
        }

        return $entries;
    }

    private function bundleQuantityFrom($group, $enrollment): int
    {
        if (! $enrollment) {
            return 1;
        }

        $reference = $enrollment->items->firstWhere('type', 'buy') ?: $enrollment->items->first();
        $row = $reference ? $group->firstWhere('item_id', $reference->item_id) : null;

        if (! $reference || ! $row || $reference->quantity <= 0) {
            return 1;
        }

        return max(1, (int) round($row->quantity / $reference->quantity));
    }

    private function enrollmentFor($offerId, ?int $storeId)
    {
        return \App\Models\BogoOfferStore::where('bogo_offer_id', $offerId)
            ->when($storeId, fn ($query) => $query->where('store_id', $storeId))
            ->with(['items', 'bogoOffer'])
            ->first();
    }
}
